<?php

namespace Sitecake\DOM;

use Sitecake\Util\Utils;

class ElementsContainer
{
    /**
     * Map of elements that page can manipulate with indexed by element identifier.
     * If there are more than one element with same name, value will be array of mappings.
     * Root elements are two array entries each with index of basic element types ('menu' and 'container')
     *
     * @var array[]
     */
    protected $elementMap = [];

    /**
     * Stores elements metadata.
     * Each entry is array with elements start position under index 0
     * and array of other metadata relevant for that specific element that can be obtained through Element::getMetadata()
     *
     * @var array
     */
    protected $elementMetadata = [];

    /**
     * Array of element objects
     *
     * @var Element[]
     */
    protected $elements = [];

    /**
     * Indicates whether keys should be preserved when returning elements from getElements method.
     * This is used only internally when calling getElements method from getElementsByName method
     *
     * @var bool
     */
    private $preserveIndexes = false;

    /**
     * Returns pattern matcher for passed element type
     *
     * @param string $type
     *
     * @return string
     */
    protected function getPattern($type)
    {
        return call_user_func(ElementFactory::getClass($type) . '::getOpenTagPattern');
    }

    /**
     * Returns tag name for elements of passed type
     *
     * @param array $matches
     * @param string $type
     *
     * @return mixed
     */
    protected function getTagName($type, $matches)
    {
        return call_user_func(ElementFactory::getClass($type) . '::getTagName', $matches);
    }

    /**
     * Returns whether element of passed type should be searched with closing tag or not
     *
     * @param string $type
     *
     * @return mixed
     */
    protected function isEmptyElement($type)
    {
        return call_user_func(ElementFactory::getClass($type) . '::isEmptyElement');
    }

    /**
     * Returns array of element in source based on passed element type with possibility for additional post filtering
     *
     * @param string $type   Type of elements to be returned.
     * @param string $source Source from where elements should be btained
     * @param mixed  $filter [optional]
     *
     * @return Element[]
     */
    public function getElements($type, $source, $filter = null)
    {
        if (!isset($this->elements[$type])) {
            if (Utils::match(
                '/' . $this->getPattern($type) . '/',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE
            )) {
                $this->__findElements($matches, $type, $source);
            }
        }

        if (isset($this->elements[$type])) {
            if ($filter !== null) {
                if (is_callable($filter)) {
                    $elements = [];
                    foreach ($this->elements[$type] as $no => $element) {
                        if ($filter($element)) {
                            $elements[$no] = $element;
                        }
                    }
                    return $this->preserveIndexes ? $elements : array_values($elements);
                }
            }

            return $this->preserveIndexes ? $this->elements[$type] : array_values($this->elements[$type]);
        }

        return [];
    }

    /**
     * Returns array of containers of certain type found in passed source and identified by passed name
     *
     * @param string|array $containerNames
     * @param string       $type
     * @param string       $source
     *
     * @return array
     */
    public function getElementsByName($containerNames, $type, $source)
    {
        $this->preserveIndexes = true;
        $elements = $this->getElements($type, $source);
        $this->preserveIndexes = false;
        if (is_string($containerNames)) {
            $mappings = [$containerNames => $this->elementMap[$type][$containerNames]];
        } else {
            $mappings = [];
            foreach ($this->elementMap[$type] as $containerName => $map) {
                if (in_array($containerName, $containerNames)) {
                    $mappings[$containerName] = $map;
                }
            }
        }

        $filtered = [];
        foreach ($mappings as $containerName => $map) {
            if (is_array($map)) {
                foreach ($map as $i => $no) {
                    if (isset($elements[$no])) {
                        $filtered[] = $elements[$no];
                    }
                }
            } else {
                if (isset($elements[$map])) {
                    $filtered[] = $elements[$map];
                }
            }
        }

        return $filtered;
    }

    /**
     * Removes element from element collection
     *
     * @param Element $element
     */
    public function removeElement(Element $element)
    {
        $map = $this->getMap($element);
        $type = $element->getType();
        if (is_array($map)) {
            foreach ($map as $index) {
                if ($element === $this->elements[$type][$index]) {
                    unset($this->elements[$type][$index]);
                    unset($this->elementMetadata[$type][$index]);
                    $map = $index;
                    break;
                }
            }
        } else {
            unset($this->elements[$type][$map]);
            unset($this->elementMetadata[$type][$map]);
        }
        unset($this->elementMap[$type][$map]);
    }

    /**
     * Adds element to element collection
     *
     * @param Element  $element
     * @param int      $startPosition
     * @param int      $endPosition
     * @param null|int $index
     */
    public function addElement(Element $element, $startPosition, $endPosition, $index = null)
    {
        $index = $index === null ? count($this->elements) - 1 : $index;
        $type = $element->getType();
        $this->elements[$type][$index] = $element;
        // Add container opening position and metadata
        $this->elementMetadata[$type][$index] = [
            $startPosition,
            $endPosition
        ];
        if ($metadata = $element->getMetadata()) {
            array_push($this->elementMetadata[$type][$index], $metadata);
        }
        // Update container map
        $this->mapElement($element, $index);
    }

    /**
     * Populates container map from passed matches
     *
     * @param array  $matches
     * @param string $type
     * @param string $source
     */
    private function __findElements($matches, $type, $source)
    {
        $counter = 0;
        foreach ($matches as $no => $match) {
            $tagName = $this->getTagName($type, $match);
            $startPosition = $match[0][1];

            if (!$this->isEmptyElement($type)) {
                $innerElementCount = 0;

                /*
                 * We search for all opened and closed tags with same tag name after container opening tag.
                 */
                $matchingTagsFound = Utils::match(
                    '/<(\/?)' . preg_quote($tagName) . '[^>]*>/',
                    $source,
                    $tags,
                    PREG_OFFSET_CAPTURE,
                    $startPosition + strlen($match[0][0])
                );

                if ($matchingTagsFound) {
                    // Go through all matching tags and find container closing tag
                    foreach ($tags as $i => $tag) {
                        $isClosingTag = $tag[1][0] === '/';
                        if (!$isClosingTag) {
                            $innerElementCount++;
                        } else {
                            if ($innerElementCount > 0) {
                                $innerElementCount--;
                            } else {
                                // This is container closing tag
                                $endPosition = $tag[0][1] + strlen('</' . $tagName . '>');
                                $length = $endPosition - $startPosition;
                                $html = mb_substr($source, $startPosition, $length);
                                // Add element to element collection
                                $element = ElementFactory::createElement($type, $html);
                                // Adds element to collection
                                $this->addElement($element, $startPosition, $endPosition, $counter);
                                $counter++;
                                break;
                            }
                        }
                    }
                }
            } else {
                // This is container closing tag
                $endPosition = $startPosition + mb_strlen($match[0][0]);
                $length = $endPosition - $startPosition;
                $html = mb_substr($source, $startPosition, $length);
                // Add element to element collection
                $element = ElementFactory::createElement($type, $html);
                // Adds element to collection
                $this->addElement($element, $startPosition, $endPosition, $counter);
                $counter++;
            }
        }
    }

    /**
     * Maps specific element inside element map to a passed index.
     * If index not passed element will be appended to end of collection
     *
     * @param Element $element Element to map
     * @param int     $index   [optional] Index to map element to
     */
    protected function mapElement(Element $element, $index = null)
    {
        $type = $element->getType();
        $identifier = $element->getIdentifier();
        if (!isset($this->elementMap[$type])) {
            $this->elementMap[$type] = [];
        }
        if (isset($this->elementMap[$type][$identifier])) {
            if (is_array($this->elementMap[$type][$identifier])) {
                array_push($this->elementMap[$type][$identifier], $index);
            } else {
                $this->elementMap[$type][$identifier] =
                    [$this->elementMap[$type][$identifier], $index];
            }
        } else {
            $this->elementMap[$type][$identifier] = $index;
        }
    }

    /**
     * Initialize element map for passed element identifier and type
     *
     * @param string       $type
     * @param string|array $identifier
     */
    protected function initElementMap($type, $identifier)
    {
        if (is_string($identifier)) {
            $this->elementMap[$type][$identifier] = -1;
        } elseif (is_array($identifier)) {
            $this->elementMap[$type] = array_fill_keys($identifier, -1);
        }
    }

    /**
     * Get mapped index for passed element
     *
     * @param Element $element
     *
     * @return array[]|int
     */
    public function getMap(Element $element)
    {
        return $this->elementMap[$element->getType()][$element->getIdentifier()];
    }

    /**
     * Returns metadata for passed element
     *
     * @param Element $element
     *
     * @return mixed
     */
    public function getMetadata(Element $element)
    {
        $type = $element->getType();
        if(isset($this->elementMap[$type][$element->getIdentifier()])) {
            $map = $this->elementMap[$type][$element->getIdentifier()];
            if (is_array($map)) {
                foreach ($map as $index) {
                    if ($element === $this->elements[$type][$index]) {
                        return $this->elementMetadata[$type][$index];
                    }
                }
            } elseif(isset($this->elementMetadata[$type][$map])) {
                return $this->elementMetadata[$type][$map];
            }
        }

        return null;
    }

    /**
     * Returns start position for passed element
     *
     * @param \Sitecake\DOM\Element $element
     *
     * @return null|int
     */
    public function getStartPosition(Element $element) {
        if ($metadata = $this->getMetadata($element)) {
            return $metadata[0];
        }

        return null;
    }

    /**
     * Returns end position for passed element
     *
     * @param \Sitecake\DOM\Element $element
     *
     * @return null|int
     */
    public function getEndPosition(Element $element) {
        if ($metadata = $this->getMetadata($element)) {
            return $metadata[1];
        }

        return null;
    }
}
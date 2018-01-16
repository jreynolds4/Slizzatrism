<?php

namespace Sitecake;

use Sitecake\DOM\Element;
use Sitecake\DOM\Element\Container;
use Sitecake\DOM\Element\Menu;
use Sitecake\DOM\Element\Meta;
use Sitecake\DOM\Element\Title;
use Sitecake\DOM\ElementsContainer;
use Sitecake\DOM\HTMLContentAwareTrait;
use Sitecake\Util\Beautifier;
use Sitecake\Util\HtmlUtils;

class SourceFile extends ElementsContainer
{
    use HTMLContentAwareTrait;

    const NAMED_CONTAINERS = 'named';

    const UNNAMED_CONTAINERS = 'unnamed';

    /**
     * Collection of container objects
     *
     * @var Container[]
     */
    protected $containers;

    /**
     * Collection of menu objects
     *
     * @var Menu[]
     */
    protected $menus;

    public function __construct($source)
    {
        // Store page source
        $this->loadHTML($source);
    }

    public function __toString()
    {
        return $this->getSource();
    }

    //<editor-fold desc="Containers manipulation">
    /**
     * Returns array of all Containers in passed source or filtered by passed parameter.
     * Filter can be one of pre-defined filter constants (SourceFile::NAMED_CONTAINERS, SourceFile::UNNAMED_CONTAINERS)
     * or container name or callable where each container will be passed
     *
     * @param string|callable|array|null $filter
     *
     * @return Container[]
     */
    public function containers($filter = null)
    {
        if (!isset($this->containers)) {
            $this->containers = $this->getElements(
                Container::type(),
                $this->getSource(),
                is_callable($filter) ? $filter : null
            );
        }

        if (!empty($filter) && !is_callable($filter)) {
            // Perform container filtering if callable passed
            if ($filter === self::NAMED_CONTAINERS) {
                // Return only named containers
                return $this->getNamedContainers();
            } elseif ($filter === self::UNNAMED_CONTAINERS) {
                // Return only un-named containers
                return $this->getUnNamedContainers();
            } else {
                // Return only containers with certain name
                return $this->getElementsByName($filter, Container::type(), $this->getSource());
            }
        }

        return $this->containers;
    }

    /**
     * Returns array of un-named containers
     *
     * @return array
     */
    public function getUnNamedContainers()
    {
        $unNamed = [];
        foreach ($this->containers() as $no => $container) {
            if (!$this->elementMetadata[Container::type()][$no][2]['named']) {
                $unNamed[] = $container;
            }
        }

        return $unNamed;
    }

    /**
     * Returns array of named containers
     *
     * @return array
     */
    public function getNamedContainers()
    {
        $named = [];
        foreach ($this->containers() as $no => $container) {
            if ($this->elementMetadata[Container::type()][$no][2]['named']) {
                $named[] = $container;
            }
        }

        return $named;
    }

    /**
     * Returns weather page contains specific container (does it contains .sc-content-$container container)
     *
     * @param string $container Optional. Container name to check for.
     * If not passed checks for all sc-content containers
     *
     * @return bool
     * @throws \Exception
     */
    public function hasContainer($container = '')
    {
        return (bool)preg_match(
            '/' . Container::getOpenTagPattern($container) . '/',
            $this->getSource()
        );
    }

    /**
     * Sets content for passed container names
     *
     * @param string|array $container Container name or associative array where keys are container names and values are
     * contents for each container
     * @param string|null $content [optional] Content to set
     *
     * @throws \Exception
     */
    public function setContainerContent($container, $content = null)
    {
        /* @var Container[] $containers */
        if (is_array($container)) {
            $containers = $this->getElementsByName(array_keys($container), Container::type(), $this->getSource());
        } else {
            $containers = $this->getElementsByName($container, Container::type(), $this->getSource());
        }
        foreach ($containers as $no => $containerElement) {
            $metadata = $this->getMetadata($containerElement);
            $length = $metadata[1] - $metadata[0];
            if (is_array($container)) {
                $content = $container[$containerElement->getIdentifier()];
            }
            $containerElement->setInnerHtml($content);
            $newLength = mb_strlen($this->updateElement($containerElement));
            $this->updatePositions($metadata[0], abs($length - $newLength) * ($length > $newLength ? -1 : 1));
        }
    }

    /**
     * Turns all unnamed containers (having just .sc-content class specified) in the page to named containers.
     * Method adds sc-content-<code>'-_cnt_' . mt_rand() . mt_rand()</code> class to each container that is not named.
     * As side effect 'containerNames' property is populated
     *
     * @throws \Exception
     */
    public function normalizeContainerNames()
    {
        $this->update(function ($source) {
            return preg_replace_callback(
                '/' . Container::getOpenTagPattern() . '/',
                function ($matches) {
                    if (!empty($matches[3])) {
                        $this->initElementMap(Container::type(), $matches[3]);

                        return $matches[0];
                    }
                    $containerName = Container::generateName();
                    $this->initElementMap(Container::type(), $containerName);

                    return $matches[1] . Container::BASE_CLASS . ' ' .
                        Container::BASE_CLASS . '-' . $containerName .
                        (strpos($matches[4], '-') === 0 ? substr($matches[4], 1) : $matches[4]);
                },
                $source
            );
        });
    }

    /**
     * Strips all generated container classes
     */
    public function cleanupContainerNames()
    {
        $this->source = preg_replace_callback(
            '/(<((?:\\\.|[\w-]|[^\0-\xa0])+)[^>]+)(\s' .
            preg_quote(Container::BASE_CLASS) .
            '\-_cnt_[0-9]+)/', function ($matches) {
            return $matches[1];
        }, $this->getSource());
    }

    /**
     * Returns a list of container names contained in page.
     *
     * @return array a list of container names
     */
    public function containerNames()
    {
        if (!isset($this->elementMap[Container::type()])) {
            $this->elementMap[Container::type()] = [];
            if (preg_match_all(
                '/' . Container::getOpenTagPattern() . '/',
                $this->getSource(),
                $matches
            )) {
                if (!empty($matches[3])) {
                    $this->initElementMap(Container::type(), array_unique($matches[3]));
                }
            }
        }

        return array_keys($this->elementMap[Container::type()]);
    }
    //</editor-fold>

    //<editor-fold desc="Menu related methods">
    /**
     * Returns array of all Menu objects filtered by name if parameter passed
     *
     * @param string|null $filter
     *
     * @return Menu[]
     */
    public function menus($filter = '')
    {
        if (!isset($this->menus)) {
            $this->menus = $this->getElements(Menu::type(), $this->getSource(), is_callable($filter) ? $filter : null);
        }

        if (!empty($filter) && !is_callable($filter)) {
            // Return only menus with certain name
            return $this->getElementsByName($filter, Menu::type(), $this->getSource());
        }

        return $this->menus;
    }

    /**
     * Returns weather page contains specific container (does it contains .sc-content-$container container)
     *
     * @param string $name Optional. Menu name, if not passed looks for any menu in page
     *
     * @return bool
     * @throws \Exception
     */
    public function hasMenu($name = '')
    {
        $name = !empty($name) ? '-' . $name : '';

        return (bool)preg_match(
            '/' . Menu::getOpenTagPattern($name) . '/',
            $this->getSource()
        );
    }

    /**
     * Saves menus in page based on passed arguments
     *
     * @param string $name
     * @param string $template
     * @param array|null $items
     * @param callable|null $itemProcess
     * @param string|null $activeClass
     * @param callable|null $isActive
     *
     * @return array|null
     */
    public function saveMenus(
        $name,
        $template = Menu::DEFAULT_TEMPLATE,
        $items = null,
        $itemProcess = null,
        $activeClass = null,
        $isActive = null
    ) {
        $menus = $this->menus($name);
        $processedItems = [];
        foreach ($menus as $menu) {
            // If items are passed, set them
            if ($items !== null) {
                $menu->items(
                    $items,
                    $itemProcess
                );
            }
            $processedItems = $menu->items();
            $menu->setTemplate($template);
            if ($activeClass !== null) {
                $menu->setActiveClass($activeClass);
            }

            $metadata = $this->getMetadata($menu);
            $length = $metadata[1] - $metadata[0];
            $menu->setInnerHtml($isActive);
            $newLength = mb_strlen($this->updateElement($menu));
            $this->updatePositions($metadata[0], abs($length - $newLength) * ($length > $newLength ? -1 : 1));
        }

        return $processedItems;
    }
    //</editor-fold>

    //<editor-fold desc="Title and metadata manipulation">
    /**
     * Returns the page title (the title tag).
     *
     * @return string the current value of the title tag
     */
    public function getPageTitle()
    {
        $elements = $this->getElements(Title::type(), $this->getSource());
        if (count($elements) > 0) {
            return $elements[0]->getInnerHtml();
        }

        return '';
    }

    /**
     * Sets the page title (the title tag).
     *
     * @param string $title Title to be set
     *
     * @throws \Exception
     */
    public function setPageTitle($title)
    {
        $elements = $this->getElements(Title::type(), $this->getSource());
        if (count($elements) > 0) {
            $titleElement = $elements[0];
        }
        if ($title === '') {
            // If empty value passed we need to remove title tag
            if (isset($titleElement)) {
                $this->source = preg_replace(
                    '/' . $this->getPattern($titleElement->getType()) . '\s*/',
                    '',
                    $this->getSource()
                );
                $startPosition = $this->getStartPosition($titleElement);
                $diff = -1 * mb_strlen($titleElement->outerHtml());
                $this->removeElement($titleElement);
            }
        } else {
            if (isset($titleElement)) {
                $metadata = $this->getMetadata($titleElement);
                $length = $metadata[1] - $metadata[0];
                $titleElement->setInnerHtml($title);
                $newLength = mb_strlen($this->updateElement($titleElement));
                $startPosition = $metadata[0];
                $diff = abs($length - $newLength) * ($length > $newLength ? -1 : 1);
            } else {
                $titleElement = new Title($title);
                $html = $titleElement->outerHtml();
                if ($startPosition = HtmlUtils::addCodeToHead(
                    $this->source,
                    $html
                )) {
                    $diff = mb_strlen($html);
                    // Add element
                    $this->addElement($titleElement, $startPosition, ($startPosition + $diff));
                }
            }
        }
        if (isset($startPosition) && isset($diff)) {
            $this->updatePositions($startPosition, $diff);
        }
    }

    /**
     * Reads the page description meta tag.
     *
     * @return string current description text
     * @throws \Exception
     */
    public function getPageDescription()
    {
        $elements = $this->getElements(Meta::type(), $this->getSource(), function ($element) {
            /* @var Element $element */
            return $element->getIdentifier() == 'description';
        });
        if (count($elements) > 0) {
            return $elements[0]->getInnerHtml();
        }

        return '';
    }

    /**
     * Sets the page description meta tag with the given content.
     *
     * @param string $text Description to be set
     *
     * @throws \Exception
     */
    public function setPageDescription($text)
    {
        $elements = $this->getElements(Meta::type(), $this->getSource(), function ($element) {
            /* @var Element $element */
            return $element->getIdentifier() == 'description';
        });
        if (count($elements) > 0) {
            $metaDescription = $elements[0];
        }
        if ($text === '') {
            // If empty value passed we need to remove title tag
            if (isset($metaDescription)) {
                $this->source = preg_replace(
                    '/' . $this->getPattern($metaDescription->getType()) . '\s*/',
                    '',
                    $this->getSource()
                );
                $startPosition = $this->getStartPosition($metaDescription);
                $diff = -1 * mb_strlen($metaDescription->outerHtml());
                $this->removeElement($metaDescription);
            }
        } else {
            if (isset($metaDescription)) {
                $metadata = $this->getMetadata($metaDescription);
                $length = $metadata[1] - $metadata[0];
                $metaDescription->setInnerHtml($text);
                $newLength = mb_strlen($this->updateElement($metaDescription));
                $this->updatePositions(
                    $metadata[0],
                    abs($length - $newLength) * ($length > $newLength ? -1 : 1)
                );
            } else {
                // Try to insert meta description tag into head
                $metaDescription = new Meta('description', $text);
                $html = $metaDescription->outerHtml();
                if ($startPosition = HtmlUtils::addCodeToHead(
                    $this->source,
                    $html
                )) {
                    // Add element
                    $diff = mb_strlen($html);
                    $this->addElement($metaDescription, $startPosition, ($startPosition + $diff));
                }
            }
        }
        if (isset($startPosition) && isset($diff)) {
            $this->updatePositions($startPosition, $diff);
        }
    }
    //</editor-fold>

    //<editor-fold desc="Source and metadata manipulation">
    /**
     * Updates page source.
     * If string is passed, page source will be updated with that string,
     * If callable is passed source will be updated with result of callable if string
     * or object that implements method __toString is returned.
     *
     * @param string|callable $source
     */
    public function update($source)
    {
        if (is_string($source)) {
            $this->source = $source;
        } elseif (is_callable($source)) {
            $result = $source($this->getSource());
            if (is_string($result) || is_object($result) && method_exists($result, '__toString')) {
                $this->source = (string)$result;
            }
        }
    }

    /**
     * Updates file source for passed container
     *
     * @param Element $element Element to update in source
     * @param bool $beautify [optional] Indicates whether content should be beautified or not. TRUE by default
     *
     * @return string New element's HTML after indentation is applied
     */
    protected function updateElement(Element $element, $beautify = true)
    {
        $metadata = $this->getMetadata($element);
        $contentBefore = mb_substr($this->getSource(), 0, $metadata[0]);
        $contentAfter = mb_substr($this->getSource(), $metadata[1]);
        if ($beautify) {
            $content = Beautifier::indent((string)$element, $this->getIndent($metadata[0]), true);
        } else {
            $content = (string)$element;
        }
        $this->source = $contentBefore . $content . $contentAfter;
        return $content;
    }

    /**
     * Returns indentation for element starting on passed position
     *
     * @param int $position
     *
     * @return bool|string
     */
    public function getIndent($position)
    {
        $haystack = mb_substr($this->source, 0, $position + 1);
        $lines = preg_split('/\R/', $haystack);

        if (is_array($lines) && count($lines) > 1) {
            $line = array_pop($lines);
            if (preg_match('/^[\t ]/', $line)) {
                return substr($line, 0, -1);
            }
        }

        return '';
    }

    /**
     * Updates start positions for all elements that are positioned after passed start position
     *
     * @param int $startPosition
     * @param int $diff
     */
    protected function updatePositions($startPosition, $diff)
    {
        foreach ($this->elementMetadata as $type => &$metadataByType) {
            foreach ($metadataByType as &$metadata) {
                if ($metadata[0] > $startPosition) {
                    $metadata[0] += $diff;
                    $metadata[1] += $diff;
                }
            }
        }
    }
    //</editor-fold>
}

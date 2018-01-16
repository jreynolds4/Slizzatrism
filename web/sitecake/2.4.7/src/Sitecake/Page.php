<?php

namespace Sitecake;

use Sitecake\DOM\Element\Link;
use Sitecake\DOM\Element\Meta;
use Sitecake\Util\HtmlUtils;
use Sitecake\Util\Utils;

class Page extends SourceFile
{
    /**
     * Draft constructor.
     *
     * @param string $source
     */
    public function __construct($source)
    {
        parent::__construct($source);
        $this->source = $this->evaluate($this->source);
    }

    /**
     * Adds data-pageid attribute to sitecake meta tag
     * Needed for SC editor to work properly.
     *
     * @param int $id ID to set
     *
     * @throws \Exception
     */
    public function setPageId($id)
    {
        $metaExists = false;
        $isset = false;
        $meta = $this->getElements(Meta::type(), $this->source, function ($element) {
            /* @var Meta $element */
            return $element->getIdentifier() === 'application-name';
        });
        if (count($meta) > 0) {
            $meta = $meta[0];
            $metaExists = true;
            $isset = $meta->getDataAttribute('pageid') !== '';
        } else {
            $meta = new Meta('application-name', 'sitecake');
        }
        if (!$metaExists) {
            $meta->setDataAttribute('pageid', $id);
            $code = $meta->outerHtml();
            if ($startPosition = HtmlUtils::addCodeToHead(
                $this->source,
                $code
            )) {
                $length = mb_strlen($code);
                // Add element
                $this->addElement($meta, $startPosition, ($startPosition + $length));
                $this->updatePositions($startPosition, $length);
            }
        } elseif (!$isset) {
            $metadata = $this->getMetadata($meta);
            $length = $metadata[1] - $metadata[0];
            $meta->setDataAttribute('pageid', $id);
            $newLength = mb_strlen($this->updateElement($meta, false));
            $this->updatePositions($metadata[0], abs($length - $newLength) * ($length > $newLength ? -1 : 1));
        }
    }

    /**
     * Adds the 'noindex, nofollow' meta tag to draft header, if not present.
     */
    public function addMetaRobots()
    {
        $meta = $this->getElements(Meta::type(), $this->source, function ($element) {
            /* @var Meta $element */
            return $element->getIdentifier() === 'robots';
        });
        if (!$meta) {
            $meta = new Meta('robots', 'noindex, nofollow');
            $code = $meta->outerHtml();
            if ($startPosition = HtmlUtils::addCodeToHead(
                $this->source,
                $code
            )) {
                $length = mb_strlen($code);
                // Add element
                $this->addElement($meta, $startPosition, ($startPosition + $length));
                $this->updatePositions($startPosition, $length);
            }
        }
    }

    /**
     * Renders evaluated page
     *
     * @return string
     * @throws \Exception
     */
    public function render()
    {
        return (string)$this;
    }

    /**
     * Checks whether passed container is inside editable container
     *
     * @param \Sitecake\DOM\Element\Link $link
     *
     * @return bool
     */
    protected function isWithinEditableContainer(Link $link)
    {
        $containers = $this->containers();
        foreach ($containers as $container) {
            $linkStartPosition = $this->getStartPosition($link);
            $containerStartPosition = $this->getStartPosition($container);
            if ($containerStartPosition < $linkStartPosition) {
                $containerEndPosition = $this->getEndPosition($container);
                if ($linkStartPosition < $containerEndPosition) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Turns all site links that are not in editable containers to editable links
     *
     * @param string $entryPointPath Path/Name to sitecake entry point
     * @param callable $callback Optional. Callback to be called on each link
     *
     * @throws \Exception
     */
    public function adjustLinks($entryPointPath, $callback = null)
    {
        /* @var Link[] $allLinks */
        $allLinks = $this->getElements(Link::type(), $this->getSource());
        foreach ($allLinks as $linkElement) {
            if ($this->isWithinEditableContainer($linkElement)) {
                continue;
            }
            $href = $linkElement->getAttribute('href');
            if (Utils::isResourceUrl($href) && !Utils::isScResourceUrl($href)) {
                // Preserve query string in link if present
                if (strpos($href, '?') !== false) {
                    list($href, $query) = explode('?', $href);
                }

                // Check if callback is passed
                if (is_callable($callback)) {
                    $path = $callback($href);

                    if ($path === false) {
                        continue;
                    }
                } else {
                    $path = $href;
                }

                $metadata = $this->getMetadata($linkElement);
                $length = $metadata[1] - $metadata[0];
                $linkElement->setAttribute('href',
                    $entryPointPath . '?scpage=' . $path . (isset($query) ? '&' . $query : ''));
                $newLength = mb_strlen($this->updateElement($linkElement, false));
                $this->updatePositions($metadata[0], abs($length - $newLength) * ($length > $newLength ? -1 : 1));
            }
        }
    }

    /**
     * Appends passed html code to draft header.
     * Used to add sitecake client side script
     *
     * @param string $code
     *
     * @throws \Exception
     */
    public function appendCodeToHead($code)
    {
        HtmlUtils::addCodeToHead($this->source, $code);
    }
}

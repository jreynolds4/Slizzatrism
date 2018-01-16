<?php

namespace Sitecake\DOM;

use Sitecake\Exception\Exception;
use Sitecake\Util\HtmlUtils;

/**
 * Class Element
 *
 * @method \DOMNodeList getElementsByTagName ( string $name )
 * @method bool isSameNode ( \DOMNode $node )
 * @package Sitecake\DOM
 */
abstract class Element implements ElementMatcher
{
    /**
     * @var \DOMElement
     */
    protected $node;

    /**
     * Element identifier
     *
     * @var string
     */
    protected $identifier;

    /**
     * Internal identifier used to distinguish elements with same identifier
     *
     * @var string
     */
    protected $uid;

    /**
     * Element type
     *
     * @var string
     */
    protected static $type = 'element';

    /**
     * Element constructor.
     *
     * @param null|string $html
     */
    public function __construct($html)
    {
        $this->node = HtmlUtils::htmlToNode($html);
        $this->identifier = $this->findIdentifier();
        $this->uid = uniqid($this->identifier);
    }

    abstract protected function findIdentifier();

    public static function type()
    {
        return static::$type;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function outerHtml()
    {
        return $this->node->ownerDocument->saveHtml($this->node);
    }

    public function getInnerHtml()
    {
        $innerHTML = '';
        $children = $this->node->childNodes;

        foreach ($children as $child) {
            $innerHTML .= $this->node->ownerDocument->saveHTML($child);
        }

        return $innerHTML;
    }

    /**
     * Sets inner HTML of an element.
     *
     * @param $content
     */
    public function setInnerHtml($content)
    {
        // First, empty the element
        for ($i = $this->node->childNodes->length - 1; $i >= 0; $i--) {
            $this->node->removeChild($this->node->childNodes->item($i));
        }
        HtmlUtils::appendHTML($this->node, $content);
    }

    public function hasAttribute($attribute)
    {
        return $this->node->hasAttribute($attribute);
    }

    public function getAttribute($attribute)
    {
        return $this->node->getAttribute($attribute);
    }

    public function getDataAttribute($attribute)
    {
        return $this->getAttribute('data-' . $attribute);
    }

    public function setAttribute($attribute, $value)
    {
        return $this->node->setAttribute($attribute, $value);
    }

    public function setDataAttribute($attribute, $value)
    {
        return $this->setAttribute('data-' . $attribute, $value);
    }

    public function __toString()
    {
        return $this->outerHtml();
    }

    public function getType()
    {
        return self::type();
    }

    public function getMetadata() {
        return [];
    }

    public function __call($name , array $arguments) {
        if (method_exists($this->node, $name)) {
            return call_user_func_array([$this->node, $name], $arguments);
        }

        throw new Exception('Call to undefined function');
    }
}
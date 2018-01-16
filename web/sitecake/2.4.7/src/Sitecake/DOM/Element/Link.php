<?php

namespace Sitecake\DOM\Element;


use Sitecake\DOM\Element;

class Link extends Element
{
    protected static $type = 'link';

    /**
     * Meta constructor.
     *
     * @param null|string $html
     */
    public function __construct($html)
    {
        if (preg_match('/' . self::getOpenTagPattern() . '/', $html)) {
            parent::__construct($html);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getOpenTagPattern($identifier = '')
    {
        return '<a\s[^>]*>';
    }

    /**
     * {@inheritdoc}
     */
    public static function getTagName($matches)
    {
        return 'a';
    }

    /**
     * @{@inheritdoc}
     */
    public static function isEmptyElement()
    {
        return false;
    }


    /**
     * {@inheritdoc}
     */
    public function findIdentifier()
    {
        $id = $this->getAttribute('id');
        return !empty($id) ? $id : uniqid($this->getType());
    }
}
<?php

namespace Sitecake\DOM\Element;


use Sitecake\DOM\Element;

class Meta extends Element
{
    protected $content;

    protected static $type = 'meta';

    /**
     * Meta constructor.
     *
     * @param null|string $html
     * @param null|string $content
     */
    public function __construct($html, $content = null)
    {
        if (preg_match('/' . self::getOpenTagPattern() . '/', $html)) {
            parent::__construct($html);
        } elseif ($content !== null) {
            parent::__construct('<meta name="' . $html . '" content="' . $content . '">');
        }

        $this->content = $this->getAttribute('content');
    }

    /**
     * {@inheritdoc}
     */
    public static function getOpenTagPattern($identifier = '')
    {
        $identifier = !empty($identifier) ? preg_quote($identifier) : '[^"|\']+';
        return '<meta[\x20\t\r\n\f]+name[\x20\t\r\n\f]*=[\x20\t\r\n\f]*["|\'](' . $identifier .
            ')["|\'][\x20\t\r\n\f]+content[\x20\t\r\n\f]*=[\x20\t\r\n\f]*((?:")([^"]*)(?:")|(?:\')([^\']*)(?:\'))[^>]*>';
    }

    /**
     * {@inheritdoc}
     */
    public static function getTagName($matches)
    {
        return 'meta';
    }

    /**
     * @{@inheritdoc}
     */
    public static function isEmptyElement()
    {
        return true;
    }


    /**
     * {@inheritdoc}
     */
    public function findIdentifier()
    {
        return $this->getAttribute('name');
    }

    /**
     * {@inheritdoc}
     */
    public function setInnerHtml($content)
    {
        $this->setAttribute('content', $content);
    }

    /**
     * {@inheritdoc}
     */
    public function getInnerHtml()
    {
        return $this->content;
    }
}
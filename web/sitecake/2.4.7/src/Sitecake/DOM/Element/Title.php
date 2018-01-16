<?php

namespace Sitecake\DOM\Element;


use Sitecake\DOM\Element;

class Title extends Element
{
    /**
     * Title text
     *
     * @var string
     */
    protected $title;

    /**
     * Element type
     *
     * @var string
     */
    protected static $type = 'general';

    /**
     * Meta constructor.
     *
     * @param null|string $html
     */
    public function __construct($html)
    {
        if (preg_match('/' . self::getOpenTagPattern() . '/', $html)) {
            parent::__construct($html);
        } else {
            parent::__construct('<title>' . $html . '</title>');
        }

        $this->title = $this->node->textContent;
    }

    /**
     * {@inheritdoc}
     */
    public static function getOpenTagPattern($identifier = '')
    {
        return '<title[^>]*>';
    }

    /**
     * {@inheritdoc}
     */
    public static function getTagName($matches)
    {
        return 'title';
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
        return 'title';
    }

    /**
     * {@inheritdoc}
     */
    public function outerHtml()
    {
        return '<title>' . $this->title . '</title>';
    }

    /**
     * {@inheritdoc}
     */
    public function getInnerHtml() {
        return $this->title;
    }

    /**
     * {@inheritdoc}
     */
    public function setInnerHtml($content)
    {
        $this->title = $this->node->textContent = $content;
    }
}
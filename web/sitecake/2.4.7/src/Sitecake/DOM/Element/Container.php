<?php

namespace Sitecake\DOM\Element;

use Sitecake\DOM\Element;

class Container extends Element
{
    const BASE_CLASS = 'sc-content';

    const GENERATED_NAME_PATTERN = '/_cnt_[0-9]+/';

    /**
     * Element type
     *
     * @var string
     */
    protected static $type = 'container';

    /**
     * Generates random container name
     *
     * @return string
     */
    public static function generateName()
    {
        return '_cnt_' . mt_rand() . mt_rand();
    }

    /**
     * @{@inheritdoc}
     */
    public static function getOpenTagPattern($identifier = '')
    {
        if (!empty($identifier)) {
            return '(<((?:\\\.|[\w-]|[^\0-\xa0])+)[^>]+(?:\s|"|\'))' .
                preg_quote(self::BASE_CLASS . '-' . $identifier) . '([^>]+>)';
        }

        return '(<((?:\\\.|[\w-]|[^\0-\xa0])+)[^>]+(?:\s|"|\'))' . preg_quote(self::BASE_CLASS) . '(?:\-([^\s"\']+))?([^>]+>)';
    }

    /**
     * {@inheritdoc}
     */
    public static function getTagName($matches)
    {
        return $matches[2][0];
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
    protected function findIdentifier()
    {
        if(preg_match(
            '/' . preg_quote(self::BASE_CLASS) . '\-([^\s]+)/',
            $this->getAttribute('class'),
            $matches
        )) {
            $name =  $matches[1];
        } else {
            $name = self::generateName();
            $this->setAttribute(
                'class',
                $this->getAttribute('class') . ' ' . self::BASE_CLASS . '-' . $name
            );
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        return ['named' => !((bool)preg_match(self::GENERATED_NAME_PATTERN, $this->getIdentifier()))];
    }
}
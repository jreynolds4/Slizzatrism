<?php

namespace Sitecake\DOM;

interface ElementMatcher
{
    /**
     * Returns regular expression to match specific element within HTML source.
     * Can accept optional parameter to build pattern for element based on identifier
     *
     * @param string $identifier
     *
     * @return  string
     */
    static function getOpenTagPattern($identifier = '');

    /**
     * Returns element tag name based on regular expression returned from getOpenTagPattern method
     *
     * @param array $matches
     *
     * @return mixed
     */
    static function getTagName($matches);

    /**
     * Returns whether element should be searched with closing tag or not
     *
     * @return bool
     */
    static function isEmptyElement();
}
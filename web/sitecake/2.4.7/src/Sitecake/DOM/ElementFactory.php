<?php
/**
 * Created by PhpStorm.
 * User: predragleka
 * Date: 9/29/2017
 * Time: 16:31
 */

namespace Sitecake\DOM;


use Sitecake\Exception\InvalidElementTypeException;
use Sitecake\Exception\UnregisteredElementTypeException;

class ElementFactory
{
    public static $elementTypes = [];

    public static function registerElementType($type, $class)
    {
        if (!is_subclass_of($class, Element::class)) {
            throw new InvalidElementTypeException($class);
        }
        self::$elementTypes[$type] = $class;
    }

    /**
     * Creates new element instance of a passed type
     *
     * @param string $type
     * @param string $html
     *
     * @return Element
     */
    public static function createElement($type, $html)
    {
        if (!isset(self::$elementTypes[$type])) {
            throw new UnregisteredElementTypeException($type);
        }

        $class = self::$elementTypes[$type];
        return new $class($html);
    }

    public static function getClass($type)
    {
        if (!isset(self::$elementTypes[$type])) {
            throw new UnregisteredElementTypeException($type);
        }

        return self::$elementTypes[$type];
    }
}
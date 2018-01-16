<?php

namespace Sitecake\Services;

use ReflectionClass;
use Silex\Application;

class ServiceRegistry
{
    protected static $namespace = '\Sitecake\Services';

    /**
     * @var Application Contains references to loaded services
     */
    protected static $context;

    public static function initialize($context = [])
    {
        self::$context = $context;
    }

    public static function get($name)
    {
        if (isset(self::$context[$name])) {
            return self::$context[$name];
        }

        $normalized = self::normalizeServiceName($name);
        $reflection = new ReflectionClass(
            self::$namespace . '\\' . $normalized . '\\' . $normalized . 'Service'
        );

        self::$context[$name] = function () use ($reflection) {
            return $reflection->newInstance(self::$context);
        };

        return self::$context[$name];
    }

    public static function normalizeServiceName($name)
    {
        return ucfirst(substr($name, 1));
    }
}

<?php

namespace Sitecake\ServiceProviders;

use Pimple\Container;
use Silex\Provider\SessionServiceProvider as Provider;
use Sitecake\ServiceProviders\Session\MemcacheExtension;

class SessionServiceProvider extends Provider
{
    public function register(Container $app)
    {
        parent::register($app);

        if (isset($app['session.save_handler']) && $app['session.save_handler'] != 'files') {
            if (is_null($app['session.save_handler'])) {
                $app['session.storage.handler'] = null;
            } else {
                $availableSessionHandlers = ['memcache', 'memcached', 'redis'];
                if (in_array($app['session.save_handler'], $availableSessionHandlers)) {
                    $app['session.storage.handler'] = function ($app) {
                        $class = ucfirst($app['session.save_handler']);

                        if (!class_exists($class)) {
                            throw new \RuntimeException(sprintf(
                                'PHP does not have "%s" session module registered',
                                $app['session.save_handler']
                            ));
                        }

                        $sessionHandler =
                            "Symfony\\Component\\HttpFoundation\\Session\\Storage\\Handler\\{$class}SessionHandler";

                        if ($class == 'Redis') {
                            // Check if server details passed
                            $server = ['127.0.0.1', 6379]; // Default server host and port
                            if (isset($app['session.options']['server'])) {
                                $server = $app['session.options']['server'];
                            }

                            $options = [
                                'key_prefix' => 'sc'
                            ];

                            // Check if prefix passed
                            if (isset($app['session.options']['prefix'])) {
                                $options['key_prefix'] = $app['session.options']['prefix'];
                            }

                            // Check if lifetime passed
                            $sessionTimeout = 60 * 60 * 24;// Default lifetime is 1 day

                            $redis = new \Redis();
                            $redis->connect($server[0], $server[1]);

                            return new $sessionHandler($redis,
                                (isset($app['session.options']['expiretime']) ?
                                    $app['session.options']['expiretime'] : $sessionTimeout), $options);
                        } else {
                            // Check if server details passed
                            $servers = [['127.0.0.1', 11211]]; // Default server host and port
                            if (isset($app['session.options']['servers'])) {
                                $servers = $app['session.options']['servers'];
                            }

                            $options = [
                                'prefix' => 'sc',
                                'expiretime' => 60 * 60 * 24 // Default lifetime is 1 day
                            ];

                            // Check if prefix passed
                            if (isset($app['session.options']['prefix'])) {
                                $options['prefix'] = $app['session.options']['prefix'];
                            }

                            // Check if lifetime passed
                            if (isset($app['session.options']['expiretime'])) {
                                $options['expiretime'] = $app['session.options']['expiretime'];
                            }

                            $app->register(new MemcacheExtension(), [
                                'memcache.library' => $app['session.save_handler'],
                                'memcache.server' => $servers
                            ]);

                            return new $sessionHandler($app['memcache'], $options);
                        }
                    };
                }
            }
        } elseif (isset($app['session.options'])) {
            if (!empty($app['session.options']['save_path'])) {
                $app['session.storage.save_path'] = $app['session.options']['save_path'];
            } else {
                // Check if php have privileges to write into session storage path, if not set it to sitecake-temp
                $savePath = ini_get('session.save_path');
                if (empty($savePath) || !@is_writable($savePath)) {
                    $savePath = $app['BASE_DIR'] . DIRECTORY_SEPARATOR . $app['site']->tmpPath();
                    if (DIRECTORY_SEPARATOR !== '/') {
                        $savePath = str_replace('/', DIRECTORY_SEPARATOR, $savePath);
                    }
                    $app['session.storage.save_path'] = $savePath;
                }
            }

            $app['session.storage.options'] = $app['session.options'];
        }
    }
}

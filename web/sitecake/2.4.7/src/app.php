<?php
/**
 * @var Silex\Application $app
 */

require __DIR__ . '/../config/bootstrap.php';

use Sitecake\Error\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$handler = function (Silex\Application $app, Request $request) {
    try {
        $dispatcher = new Sitecake\Dispatcher($app, $app['sm']);

        return $dispatcher->dispatch($request);
    } catch (\Sitecake\Exception\InternalException $e) {
        Sitecake\Log\Log::write('error', (string)$e);

        return new Response(ErrorHandler::handleException($e), 500);
    } catch (\Exception $e) {
        Sitecake\Log\Log::write('error', (string)$e);
        $code = $e->getCode();

        // TODO : Check if there is more codes used in app
        $possibleCodes = [400, 401, 403, 404, 405, 500];

        // TODO : Need to send real http status when new client is implemented
        if (empty($code) || !in_array($code, $possibleCodes)) {
            $code = 500;
        }

        return new Response(ErrorHandler::handleException($e), $code);
    }
};

$app->match('/', $handler)->method("GET");
$app->match('/', $handler)->method("POST");

if ($app['environment'] == 'test') {
    return $app;
} else {
    $app->run();
}

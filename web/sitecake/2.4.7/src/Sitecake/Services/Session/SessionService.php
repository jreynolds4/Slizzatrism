<?php

namespace Sitecake\Services\Session;

use Silex\Application;
use Sitecake\Exception\MissingArgumentsException;
use Sitecake\Services\Service;

class SessionService extends Service
{
    /**
     * @var Application
     */
    protected $ctx;

    public function __construct($ctx)
    {
        $this->ctx = $ctx;
    }

    public function isAuthRequired($action)
    {
        return !($action === 'login' || $action === 'change');
    }

    public function login($request)
    {
        $credentials = $request->query->get('credentials');
        $status = $this->ctx['sm']->login($credentials);

        return $this->json($request, ['status' => $status], 200);
    }

    public function change($request)
    {
        $credentials = $request->query->get('credentials');
        $newCredentials = $request->query->get('newCredentials');

        if (is_null($credentials)) {
            throw new MissingArgumentsException(['name' => 'credentials'], 400);
        }

        if (is_null($newCredentials)) {
            throw new MissingArgumentsException(['name' => 'newCredentials'], 400);
        }

        if ($this->ctx['auth']->authenticate($credentials)) {
            $this->ctx['auth']->setCredentials($newCredentials);
            $status = 0;
        } else {
            $status = 1;
        }

        return $this->json($request, ['status' => $status], 200);
    }

    public function logout($request)
    {
        $this->ctx['sm']->logout();

        return $this->json($request, ['status' => 0], 200);
    }

    public function alive($request)
    {
        $this->ctx['sm']->alive();

        return $this->json($request, ['status' => 0], 200);
    }
}

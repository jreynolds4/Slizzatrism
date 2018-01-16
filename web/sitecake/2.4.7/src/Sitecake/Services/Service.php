<?php

namespace Sitecake\Services;

use Symfony\Component\HttpFoundation\JsonResponse;

abstract class Service
{
    public function isAuthRequired($action)
    {
        return true;
    }

    public function actionExists($action)
    {
        return method_exists($this, $action);
    }

    protected function json($req, $data, $status = 200)
    {
        $resp = new JsonResponse($data, $status);

        if ($req->query->has('callback')) {
            $resp->setCallback($req->query->get('callback'));
        }

        return $resp;
    }
}

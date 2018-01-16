<?php

namespace Sitecake\Auth;

interface AuthInterface
{
    public function authenticate($credentials);

    public function setCredentials($credentials);
}

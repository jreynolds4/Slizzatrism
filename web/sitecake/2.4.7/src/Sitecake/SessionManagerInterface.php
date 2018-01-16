<?php

namespace Sitecake;

interface SessionManagerInterface
{

    /**
     * Checks if user is logged in.
     *
     * @return boolean true if user is logged in
     */
    public function isLoggedIn();

    public function login($credentials);

    public function logout();

    public function alive();
}

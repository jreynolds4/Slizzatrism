<?php

namespace Sitecake;

use Sitecake\Auth\AuthInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionManager implements SessionManagerInterface
{
    const SESSION_TIMEOUT = 10000;

    protected $session;

    protected $fileLock;

    protected $auth;

    /**
     * @var Site
     */
    protected $site;

    public function __construct(SessionInterface $session, FileLock $fileLock, AuthInterface $auth, $site)
    {
        $this->session = $session;
        $this->fileLock = $fileLock;
        $this->auth = $auth;
        $this->site = $site;
    }

    public function login($credentials)
    {
        if ($this->isLoggedIn()) {
            return 0;
        }

        if ($this->auth->authenticate($credentials)) {
            if ($this->fileLock->exists('login')) {
                return 2;
            } else {
                $this->session->set('loggedin', true);
                $this->fileLock->set('login', self::SESSION_TIMEOUT);
                $this->site->editSessionStart();

                return 0;
            }
        } else {
            return 1;
        }
    }

    /**
     * Checks if the current user is logged in.
     *
     * @return boolean returns true if user is logged in.
     */
    public function isLoggedIn()
    {
        return $this->session->has('loggedin');
    }

    public function logout()
    {
        $this->session->invalidate(0);
        $this->fileLock->remove('login');
    }

    public function alive()
    {
        if ($this->isLoggedIn()) {
            $this->fileLock->set('login', self::SESSION_TIMEOUT);
        }
    }
}

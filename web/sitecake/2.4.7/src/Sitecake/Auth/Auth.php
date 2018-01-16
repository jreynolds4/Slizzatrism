<?php

namespace Sitecake\Auth;

use League\Flysystem\Filesystem;

class Auth implements AuthInterface
{

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var string Path to file with credentials
     */
    protected $credentialsFile;

    /**
     * @var string Credential string
     */
    protected $credentials;

    public function __construct(Filesystem $fs, $credentialsFile)
    {
        $this->fs = $fs;
        $this->credentialsFile = $credentialsFile;
        $this->readCredentials();
    }

    protected function readCredentials()
    {
        $txt = $this->fs->read($this->credentialsFile);
        preg_match_all('/\$credentials\s*=\s*"([^"]+)"/', $txt, $matches);
        $this->credentials = $matches[1][0];
    }

    public function authenticate($credentials)
    {
        return ($credentials === $this->credentials);
    }

    public function setCredentials($credentials)
    {
        $this->credentials = $credentials;
        $this->fs->put($this->credentialsFile, '<?php $credentials = "' . $credentials . '";');
    }
}

<?php

namespace Sitecake\Exception;

class MissingServiceException extends Exception
{
    protected $messageTemplate = 'Service class %s could not be found';
}

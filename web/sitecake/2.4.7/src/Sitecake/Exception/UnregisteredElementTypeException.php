<?php

namespace Sitecake\Exception;

class UnregisteredElementTypeException extends Exception
{
    protected $messageTemplate = 'Truing to access or instantiate unregistered element type "%s"';
}

<?php

namespace Sitecake\Exception;

class InvalidElementTypeException extends Exception
{
    protected $messageTemplate = 'Trying to register invalid element type. "%s" has to extend Sitecake\DOM\Element';
}

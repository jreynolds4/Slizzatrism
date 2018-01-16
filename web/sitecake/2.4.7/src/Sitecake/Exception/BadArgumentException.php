<?php

namespace Sitecake\Exception;

class BadArgumentException extends Exception
{
    protected $messageTemplate = 'Argument \'%s\' is not formatted right.';
}

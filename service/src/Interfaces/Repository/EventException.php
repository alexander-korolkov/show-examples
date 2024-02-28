<?php


namespace Fxtm\CopyTrading\Interfaces\Repository;


use Throwable;

class EventException extends \Exception
{

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        if($previous == null) {
            parent::__construct($message, $code, $previous);
        }
        else{
            parent::__construct($message . " " . $previous->getMessage() . " " . $previous->getTraceAsString());
        }
    }
}
<?php


namespace Fxtm\CopyTrading\Domain\Model\Leader;


use RuntimeException;
use Throwable;

class LeaderRepositoryException extends RuntimeException
{

    public function __construct(Throwable $previous = null)
    {
        parent::__construct('Leaders repository fail', 0, $previous);
    }

}
<?php


namespace Fxtm\CopyTrading\Domain\Model\Follower;


use RuntimeException;
use Throwable;

class FollowersRepositoryException extends RuntimeException
{

    public function __construct(Throwable $previous = null)
    {
        parent::__construct('Followers repository fail', 0, $previous);
    }

}
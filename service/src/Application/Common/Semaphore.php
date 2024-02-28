<?php


namespace Fxtm\CopyTrading\Application\Common;


interface Semaphore
{
    public function acquire(string $id, int $timeout = 3): bool;
    public function release(string $id);
}
<?php

namespace Fxtm\CopyTrading\Application\Common;

interface Locker
{
    /**
     * Try to lock given process
     * returns false if it already locked
     *
     * @param string $processName
     * @return bool
     */
    public function lock(string $processName): bool;

    /**
     * Returns content of lock file
     *
     * @param string $processName
     * @return string
     */
    public function getLockInfo(string $processName): string;

    /**
     * Try to unlock given process
     *
     * @param string $processName
     * @return bool
     */
    public function unlock(string $processName): bool;
}

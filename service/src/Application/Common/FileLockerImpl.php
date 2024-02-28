<?php

namespace Fxtm\CopyTrading\Application\Common;

class FileLockerImpl implements Locker
{
    /**
     * @var array
     */
    private static $locks = [];

    /**
     * Try to lock given process
     * returns false if it already locked
     *
     * @param string $processName
     * @return bool
     */
    public function lock(string $processName): bool
    {
        if (isset(self::$locks[$processName])) {
            return false;
        }
        
        $fileName = $this->getLockFilePath($processName);
        $lock = fopen($fileName, 'c+');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            return false;
        }

        self::$locks[$processName] = $lock;
        $this->fillLockFile($lock);

        return true;
    }

    /**
     * Returns full path to lock file in tmp dir
     *
     * @param string $processName
     * @return string
     */
    private function getLockFilePath(string $processName)
    {
        return sprintf('%s/%s.lock', sys_get_temp_dir(), $processName);
    }

    /**
     * Saves to lock file
     * information about process id and date of lock
     *
     * @param resource $lock
     */
    private function fillLockFile($lock)
    {
        ftruncate($lock, 0);
        rewind($lock);

        fwrite($lock, json_encode([
            'pid' => posix_getpid(),
            'started' => date('c')
        ]));
    }

    /**
     * Returns content of lock file
     *
     * @param string $processName
     * @return string
     */
    public function getLockInfo(string $processName) : string
    {
        return file_get_contents($this->getLockFilePath($processName));
    }

    /**
     * Try to unlock given process
     *
     * @param string $processName
     * @return bool
     */
    public function unlock(string $processName): bool
    {
        if (!isset(self::$locks[$processName])) {
            return true;
        }

        $lock = self::$locks[$processName];
        fclose($lock);
        unset(self::$locks[$processName]);

        return true;
    }
}

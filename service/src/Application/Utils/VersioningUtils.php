<?php

namespace Fxtm\CopyTrading\Application\Utils;

class VersioningUtils
{
    /**
     * @var string
     */
    private static $version;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * VersioningUtils constructor.
     * @param string $rootDir
     */
    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }

    /**
     * Returns current git version of the project
     *
     * @return string
     */
    public function getCurrentVersion() : string
    {
        if (!self::$version) {
            self::$version = $this->readFromFile();
        }

        return self::$version;
    }

    /**
     * Try to read version from file in root directory of the project
     *
     * @return string
     */
    private function readFromFile() : string
    {
        $file = rtrim($this->rootDir, '/') . '/version.txt';

        return is_file($file) ? file_get_contents($file) : 'undefined';
    }
}

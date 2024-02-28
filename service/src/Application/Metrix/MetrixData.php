<?php

namespace Fxtm\CopyTrading\Application\Metrix;

class MetrixData
{
    /**
     * @var string
     */
    private static $worker;

    /**
     * @param string $name
     */
    public static function setWorker($name)
    {
        self::$worker = $name;
    }

    /**
     * @return string
     */
    public static function getWorker()
    {
        return self::$worker;
    }
}

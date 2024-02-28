<?php

namespace Fxtm\CopyTrading\Application\Common;

class Environment
{
    /**
     * Return true if its on test server
     * @return bool
     *
     */
    public static function isTest()
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false;
        }

        return
            (mb_strpos($_SERVER['HTTP_HOST'], 'trunk',     0, 'utf-8') !== false) ||
            (mb_strpos($_SERVER['HTTP_HOST'], 'dev',     0, 'utf-8') !== false) ||
            (mb_strpos($_SERVER['HTTP_HOST'], 'forextime.dom',     0, 'utf-8') !== false) ||
            (mb_strpos($_SERVER['HTTP_HOST'], 'forextime-int.dom', 0, 'utf-8') !== false);
    }

    /**
     * Return true if its on test server
     * @return bool
     */
    public static function isTestUname()
    {
        return self::isTest() ||
            (mb_strpos(php_uname(), 'trunk', 0, 'utf-8') !== false) ||
            (mb_strpos(php_uname(), 'dev', 0, 'utf-8') !== false) ||
            (isset($_SERVER['HTTP_HOST']) && substr($_SERVER['HTTP_HOST'], -3) === 'dev') ||
            (isset($_SERVER["APP_ENV"]) && strtolower($_SERVER["APP_ENV"]) !== 'prod') ||
            (mb_strpos(php_uname(), 'Windows', 0, 'utf-8') === 0);
    }

    /**
     * Return true if its on staging server
     * @return bool
     *
     */
    public static function isStaging()
    {
        return
            (mb_strpos(php_uname(), 'staging', 0, 'utf-8') !== false) ||
            (isset($_SERVER['HTTP_HOST']) && strpos(strtolower($_SERVER['HTTP_HOST']), 'staging') !== false) ||
            (isset($_SERVER["APP_ENV"]) && strtolower($_SERVER["APP_ENV"]) == 'staging');
    }

    /**
     * Return true if its on prod server
     * @return bool
     *
     */
    public static function isProd()
    {
        return !self::isTestUname() && !self::isStaging();
    }
}

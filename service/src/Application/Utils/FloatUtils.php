<?php

namespace Fxtm\CopyTrading\Application\Utils;

class FloatUtils
{
    /**
     * Converts float value to clean string
     * (e.g. (float) -0.000005 => (string) "-0.000005", not "-5,05E-6")
     * Useful for operations with bcmath library
     *
     * for more information see caution on
     * https://www.php.net/manual/en/intro.bc.php
     *
     * @param float $value
     * @param int $scale
     * @return string
     */
    public static function toString($value, $scale = 6)
    {
        return number_format($value, $scale, '.', '');
    }

    /**
     * Returns given value truncated to given scale
     *
     * @param float $value
     * @param int $scale
     * @return string
     */
    public static function truncate($value, $scale = 2)
    {
        return bcadd(self::toString($value), '0', intval($scale));
    }
}

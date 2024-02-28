<?php

namespace Fxtm\CopyTrading\Domain\Common;

class Objects
{
    public static function newInstance($class, array $props = [])
    {
        $instance = unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
        if (!empty($props)) {
            $instance->fromArray($props);
        }
        return $instance;
    }
}

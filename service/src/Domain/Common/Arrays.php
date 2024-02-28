<?php

namespace Fxtm\CopyTrading\Domain\Common;

class Arrays
{
    public static function toArrayOfArrays(array $entities)
    {
        return array_map(function ($entity) {
            return $entity->toArray();
        }, $entities);
    }

    public static function fromArrayOfArrays(array $arrays, $class)
    {
        return array_map(function ($array) use ($class) {
            return Objects::newInstance($class, $array);
        }, $arrays);
    }
}

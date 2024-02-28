<?php

namespace Fxtm\CopyTrading\Application\Metrix;

interface MetrixService
{
    /**
     * @param string $reporter
     * @param float $value
     * @param array $tags
     * @param array $additional
     * @param int|null $timestamp
     * @throws \Exception
     */
    public function write($reporter, $value, $tags = [], $additional = [], $timestamp = null);
}

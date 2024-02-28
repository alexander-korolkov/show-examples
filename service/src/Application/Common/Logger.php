<?php

namespace Fxtm\CopyTrading\Application\Common;

interface Logger
{
    public function error($message, array $context = array());
    public function info($message, array $context = array());
    public function debug($message, array $context = array());
}

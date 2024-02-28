<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

interface ServerAwareAccount
{
    public function number();
    public function server();
}

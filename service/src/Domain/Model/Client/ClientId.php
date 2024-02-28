<?php

namespace Fxtm\CopyTrading\Domain\Model\Client;

use Fxtm\CopyTrading\Domain\Common\AbstractId;

class ClientId extends AbstractId
{
    public function __construct($id)
    {
        parent::__construct(intval($id));
    }
}

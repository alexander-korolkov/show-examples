<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

use Fxtm\CopyTrading\Domain\Common\AbstractId;

class AccountNumber extends AbstractId
{
    public function __construct($id)
    {
        parent::__construct(intval($id));
    }
}

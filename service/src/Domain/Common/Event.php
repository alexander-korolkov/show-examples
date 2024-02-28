<?php

namespace Fxtm\CopyTrading\Domain\Common;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface Event
{
    public static function type();
    public function getWorkflowId() : Identity;
    public function getAccountNumber() : AccountNumber;
    public function getTime() : DateTime;
    public function getType() : string;
    public function getMessage() : string;
}

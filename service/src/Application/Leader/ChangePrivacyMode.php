<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangePrivacyMode
{
    private $accNo         = null;
    private $privacyMode   = null;
    private $closeFollAccs = null;
    private $broker;

    public function __construct(AccountNumber $accNo, $privacyMode, $broker, $closeFollAccs = false)
    {
        $this->accNo         = $accNo;
        $this->broker         = $broker;
        $this->privacyMode   = intval($privacyMode);
        $this->closeFollAccs = boolval($closeFollAccs);
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getPrivacyMode()
    {
        return $this->privacyMode;
    }

    public function needsCloseFollowerAccounts()
    {
        return $this->closeFollAccs;
    }

    public function getBroker()
    {
        return $this->broker;
    }
}

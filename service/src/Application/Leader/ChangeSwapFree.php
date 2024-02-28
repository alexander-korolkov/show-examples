<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeSwapFree
{
    /**
     * @var AccountNumber
     */
    private $accNo;

    /**
     * @var string
     */
    private $broker;

    /**
     * @var bool
     */
    private $swapFree;

    public function __construct(AccountNumber $accNo, $swapFree, $broker)
    {
        $this->accNo = $accNo;
        $this->broker = $broker;
        $this->swapFree = boolval($swapFree);
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getSwapFree()
    {
        return $this->swapFree;
    }

    public function getBroker()
    {
        return $this->broker;
    }
}

<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface Transfer
{
    const STATUS_NEW = 1;
    const STATUS_TAKE = 2;

    /**
     * @return int
     */
    public function getId() : int;

    /**
     * @return int
     */
    public function getStatus() : int;

    /**
     * @return float
     */
    public function getFromAmount() : float;

    /**
     * @return string
     */
    public function getFromCurrency() : string;

    /**
     * @return float
     */
    public function getToAmount() : float;

    /**
     * @return string
     */
    public function getToCurrency() : string;

    /**
     * @return int
     */
    public function getTransferTypeId() : int;

    /**
     * @return AccountNumber
     */
    public function getFromAccountNumber() : AccountNumber;

    /**
     * @return AccountNumber
     */
    public function getToAccountNumber() : AccountNumber;
}

<?php

namespace Fxtm\CopyTrading\Interfaces\DAO\Account;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Account\AccountCandle;

interface AccountCandleDao
{
    /**
     * @param int $login
     * @return AccountCandle
     */
    public function get(int $login) : AccountCandle;

    /**
     * @param int[] $logins
     * @param DateTime $onDatetime on what datetime equities are needed
     * @return AccountCandle[]
     */
    public function getMany(array $logins, DateTime $onDatetime) : array;

}

<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

interface EquityService
{
    public function fixTransactionEquityChange(AccountNumber $accNo, Money $equity, Money $transAmt, $order, $dateTime);
    public function saveTransactionEquityChange(AccountNumber $accNo, Money $equity, Money $transAmt, $order, $dateTime);
    public function getAccountEquity(AccountNumber $accNo);
    public function getAccountDailyEquity(AccountNumber $accNo);

    /**
     * @param array $accountNumbers
     * @return array
     */
    public function getLastEquitiesByAccountsNumbersFromLocalDb(array $accountNumbers): array;

    /**
     * Returns equity changes for given accounts
     * from the MT Servers using WebGate class
     *
     * @param array $accounts
     * @param DateTime $onDatetime on what datetime equities are needed
     * @return array
     */
    public function getAccountsEquityFormWebGate(array $accounts, DateTime $onDatetime) : array;

    /**
     * Returns equities for given accounts
     * which will be used for statistics calculations
     *
     * @param array $accountNumbers
     * @return array
     */
    public function getEquityForStatistics(array $accountNumbers) : array;

    /**
     * Returns first equity row of account after he deposited at least minimum equity
     *
     * @param AccountNumber $accNo
     * @return array
     */
    public function getFirstActiveEquity(AccountNumber $accNo);

    /**
     * Returns all deposits to given account between given dates
     *
     * @param AccountNumber $accountNumber
     * @param string $start
     * @param string $end
     * @return float
     */
    public function calculateDeposits(AccountNumber $accountNumber, $start, $end);

    /**
     * Returns all withdrawals to given account between given dates
     *
     * @param AccountNumber $accountNumber
     * @param string $start
     * @param string $end
     * @return float
     */
    public function calculateWithdrawals(AccountNumber $accountNumber, $start, $end);

    /**
     * Find equity row closest by dateTime and in_out to given values
     *
     * @param AccountNumber $accountNumber
     * @param string $dateTime
     * @param float $amount
     * @return array
     */
    public function getEquityRowByFee(AccountNumber $accountNumber, $dateTime, $amount);
}

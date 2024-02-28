<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Account\TradeAccount;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;

interface TradeAccountGateway
{
    /**
     * @param LeaderAccount $leadAcc
     * @param string $broker
     * @return TradeAccount
     */
    public function createAggregateAccount(LeaderAccount $leadAcc, $broker);

    /**
     * @param ClientId $clientId
     * @param string $followerBroker
     * @param string $leaderBroker
     * @param LeaderAccount $leadAcc
     * @return TradeAccount
     */
    public function createFollowerAccount(ClientId $clientId, $followerBroker, $leaderBroker, LeaderAccount $leadAcc);

    /**
     * @param AccountNumber $accNo
     * @param string $broker
     * @return TradeAccount
     */
    public function fetchAccountByNumber(AccountNumber $accNo, $broker);

    /**
     * @param AccountNumber $accNo
     * @param $broker
     * @return TradeAccount
     */
    public function fetchAccountByNumberWithFreshEquity(AccountNumber $accNo, $broker);

    public function changeAccountReadOnly(AccountNumber $tradeAccNo, $broker, $readOnly = true);

    public function changeAccountLeverage(AccountNumber $tradeAccNo, $broker, $leverage);

    public function changeAccountSwapFree(AccountNumber $tradeAccNo, $broker, $isSwapFree);

    public function changeAccountShowEquity(AccountNumber $accNo, $broker, $showEquity);

    public function refresh(AccountNumber $tradeAccNo, $broker);

    public function destroyAccount(AccountNumber $tradeAccNo, $broker);

    /**
     * @param string $broker
     * @param string $accountNumber
     * @param float $amountDifference
     * @return array
     */
    public function adjustAggregatorBalance(string $broker, string $accountNumber, float $amountDifference): array;
}

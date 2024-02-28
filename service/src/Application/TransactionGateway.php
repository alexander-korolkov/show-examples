<?php

namespace Fxtm\CopyTrading\Application;

use Exception;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Interfaces\Gateway\Transaction\TransactionGatewayException;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface TransactionGateway
{
    const TK_DEPOSIT    = 0;
    const TK_WITHDRAWAL = 1;
    const TK_BOTH       = 2;

    /**
     * @param int $transId identifier of transaction aka transfer
     * @param string $broker broker's identifier like 'fxtm' or 'aint'
     * @param int $kind Identifier of transaction type
     * @param AccountNumber $accNo Account involved into transaction
     *
     * @return TransactionGatewayExecuteResult transaction execution status DTO, see phpdoc
     * @throws TransactionGatewayException
     */
    public function executeTransaction($transId, $broker, $kind, AccountNumber $accNo);

    /**
     * @param AccountNumber $accNo
     * @param $broker
     * @param $amount
     * @return mixed
     * @throws TransactionGatewayException
     */
    public function createTransaction(AccountNumber $accNo, $broker, $amount);

    /**
     * @param AccountNumber $accNo
     * @param $broker
     * @param $amount
     * @return mixed
     * @throws TransactionGatewayException
     */
    public function createSynchronousTransaction(AccountNumber $accNo, $broker, $amount);

    /**
     * @param $transId
     * @param $broker
     * @return mixed
     * @throws TransactionGatewayException
     */
    public function cancelTransaction($transId, $broker);

    /**
     * @param $transId
     * @param $broker
     * @return mixed
     * @throws TransactionGatewayException
     */
    public function getWithdrawalAmount($transId, $broker);

    /**
     * @param $transId
     * @param $broker
     * @return mixed
     * @throws TransactionGatewayException
     */
    public function isNoActivityWithdrawal($transId, $broker);

    /**
     * @param string $broker
     * @return array
     * @throws TransactionGatewayException
     */
    public function getTransactionsForCopyTrading($broker);

    /**
     * @param int $transferId
     * @param string $broker
     * @param string $comment
     * @throws TransactionGatewayException
     */
    public function changeExecutorToBackOffice($transferId, $broker, $comment = null);

    /**
     * @param int $transId
     * @param int $leaderAccNo
     * @param string $broker
     * @param float $amount
     * @return int
     * @throws TransactionGatewayException
     */
    public function createInnerBrokerTransaction($transId, $leaderAccNo, $broker, $amount);

    /**
     * @param string $followerBroker
     * @param string $leaderBroker
     * @param ClientId $leaderClientId
     * @param int $transId
     * @param float $amount
     * @param Currency $currency
     * @return int
     * @throws TransactionGatewayException
     */
    public function createBetweenBrokersTransaction($followerBroker, $leaderBroker, $leaderClientId, $transId, $amount, Currency $currency);

    /**
     * @param string $followerBroker
     * @param string $leaderBroker
     * @param ClientId $leaderClientId
     * @param int $transId
     * @param float $amount
     * @return int
     * @throws TransactionGatewayException
     */
    public function createBetweenBrokersTransactionSecond(
        $followerBroker,
        $leaderBroker,
        ClientId $leaderClientId,
        $transId,
        $amount
    );

    public function transferWasExecuted($transId, $broker);

    /**
     * Destination account of transfer with given $transId
     * will be changed to the owner's wallet
     * Executor will be changed to the Robot
     *
     * @param $transId
     * @param $broker
     * @throws TransactionGatewayException
     */
    public function changeDestinationToWallet($transId, $broker);
}

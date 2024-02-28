<?php

namespace Fxtm\CopyTrading\Domain\Model\Account;

use Fxtm\CopyTrading\Domain\Common\AbstractEntity;
use Fxtm\CopyTrading\Domain\Entity\TradeAccountFactory;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;


class TradeAccount extends AbstractEntity implements ServerAwareAccount
{
    private $accNo;
    private $server;
    private $accCurr;
    private $ownerId;
    private $equity = 0.00;
    private $balance = 0.00;
    private $leverage = 0.00;
    private $accountTypeId;
    private $groupName;

    private $isReadOnly = true;
    private $isSwapFree = false;

    public function __construct(
        AccountNumber $accNo,
        $server,
        Currency $accCurr,
        ClientId $ownerId,
        Money $equity,
        Money $balance,
        $leverage,
        $accountTypeId,
        $groupName = null,
        $isReadOnly = true,
        $isSwapFree = false
    ) {
        parent::__construct($accNo);

        $this->accNo = $accNo;
        $this->server = $server;
        $this->accCurr = $accCurr;
        $this->ownerId = $ownerId;
        $this->equity = $equity;
        $this->balance = $balance;
        $this->leverage = $leverage;
        $this->accountTypeId = $accountTypeId;
        $this->groupName = $groupName;

        $this->isReadOnly = boolval($isReadOnly);
        $this->isSwapFree = boolval($isSwapFree);
    }

    /**
     * Returns TradeAccount model
     * built by data from response
     * from the TradeAccountApi
     *
     * @param array $response
     * @return TradeAccount
     */
    public static function fromTradeAccountApiResponse(array $response)
    {
        $accNo = new AccountNumber($response['login']);
        $accCurr = Currency::forCode($response['currency']);
        $server = Server::byAccountType($response['account_type_id']);

        return new TradeAccount(
            $accNo,
            $server,
            $accCurr,
            new ClientId($response['client_id']),
            new Money($response['equity'], $accCurr),
            new Money($response['balance'], $accCurr),
            $response['leverage'],
            $response['account_type_id'],
            $response['group_name'],
            $response['read_only'],
            $response['is_swap_free']
        );
    }

    /**
     * @return AccountNumber
     */
    public function number()
    {
        return $this->accNo;
    }

    /**
     * @return int
     */
    public function server()
    {
        return $this->server;
    }

    /**
     * @return Currency
     */
    public function currency()
    {
        return $this->accCurr;
    }

    /**
     * @return ClientId
     */
    public function ownerId()
    {
        return $this->ownerId;
    }

    /**
     * @return Money
     */
    public function equity()
    {
        return $this->equity;
    }

    /**
     * @return Money
     */
    public function balance()
    {
        return $this->balance;
    }

    public function leverage()
    {
        return $this->leverage;
    }

    public function isReadOnly()
    {
        return $this->isReadOnly;
    }

    public function isSwapFree()
    {
        return $this->isSwapFree;
    }

    public function groupName()
    {
        return $this->groupName;
    }

    public function isLeaderAccount()
    {
        return TradeAccountFactory::isLeaderAccountType($this->accountTypeId);
    }

    public function isFollowerAccount()
    {
        return TradeAccountFactory::isFollowerAccountType($this->accountTypeId);
    }

    public function accountTypeId()
    {
        return $this->accountTypeId;
    }
}

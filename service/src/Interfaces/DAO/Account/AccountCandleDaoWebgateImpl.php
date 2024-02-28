<?php

namespace Fxtm\CopyTrading\Interfaces\DAO\Account;

use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Account\AccountCandle;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Repository\BrokerRepository;
use Memcache;

class AccountCandleDaoWebgateImpl implements AccountCandleDao
{
    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var BrokerRepository
     */
    private $brokerRepository;

    /**
     * @var Memcache
     */
    private $equityMemcache;

    /**
     * @var int
     */
    private $equityCacheExpirationTime;

    /**
     * AccountCandleDaoWebgateImpl constructor.
     * @param TradeAccountGateway $tradeAccountGateway
     * @param BrokerRepository $brokerRepository
     * @param Memcache $equityMemcache
     * @param int $equityCacheExpirationTime
     */
    public function __construct(
        TradeAccountGateway $tradeAccountGateway,
        BrokerRepository $brokerRepository,
        Memcache $equityMemcache,
        $equityCacheExpirationTime = 0
    ) {
        $this->tradeAccountGateway = $tradeAccountGateway;
        $this->brokerRepository = $brokerRepository;
        $this->equityMemcache = $equityMemcache;

        $this->equityCacheExpirationTime = $equityCacheExpirationTime;
    }


    /**
     * @inheritDoc
     */
    public function get(int $login): AccountCandle
    {
        $memcacheKey = 'fc_equity_' . $login;

        if (null !== $this->equityMemcache && $this->equityCacheExpirationTime > 0) {
            $memcacheValue = $this->equityMemcache->get($memcacheKey);
            if (false !== $memcacheValue) {
                return new AccountCandle($login, $memcacheValue);
            }
        }

        try {
            $broker = $this->brokerRepository->getByTradeAccount($login);
            $tradeAcc = $this->tradeAccountGateway->fetchAccountByNumberWithFreshEquity(new AccountNumber($login), $broker);
            $this->equityMemcache->set($memcacheKey, $tradeAcc->equity()->amount(), 0, $this->equityCacheExpirationTime);

            return new AccountCandle($login, $tradeAcc->equity()->amount());
        } catch (\Throwable $e) {
            return new AccountCandle($login, 0);
        }
    }

    /**
     * @inheritDoc
     */
    public function getMany(array $logins, DateTime $onDatetime): array
    {
        throw new \Exception("Not implemented.");
    }

}

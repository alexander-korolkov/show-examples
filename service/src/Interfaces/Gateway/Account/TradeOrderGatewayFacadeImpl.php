<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;

use Fxtm\CopyTrading\Application\TradeOrderGateway;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;
use Fxtm\CopyTrading\Interfaces\DAO\Account\AccountCandleDaoFactory;
use Psr\Log\LoggerInterface;

class TradeOrderGatewayFacadeImpl implements TradeOrderGatewayFacade
{
    private static $tradingDataGateways = [];

    /**
     * @var DataSourceFactory
     */
    private $factory;

    /**
     * @var AccountCandleDaoFactory
     */
    private $accountCandleDaoFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $frsMap;

    public function __construct(
        DataSourceFactory $factory,
        AccountCandleDaoFactory $accountCandleDaoFactory,
        LoggerInterface $logger,
        array $frsMap
    ) {
        $this->factory = $factory;
        $this->accountCandleDaoFactory = $accountCandleDaoFactory;
        $this->logger = $logger;
        $this->frsMap = $frsMap;
    }

    /**
     * @param int $server
     * @return TradeOrderGateway
     */
    public function getForServer(int $server): TradeOrderGateway
    {
        if (!isset($this->frsMap[$server])) {
            throw new \InvalidArgumentException("Unknown server ID");
        }

        if (empty(self::$tradingDataGateways[$server])) {
            if (in_array($server, Server::mt4Servers())) {
                self::$tradingDataGateways[$server] =
                    //TODO I have return ARS implementation because FRS cannot be used for getting MT4 trading history (shrinker)
                    new TradeOrderARSMT4GatewayImpl(
                        $this->factory->getArsConnection($server),
                        $this->accountCandleDaoFactory->create($server),
                        $this->logger
                    );
//                self::$tradingDataGateways[$server] =
//                    (new TradeOrderFRSMT4GatewayImpl(
//                        $this->factory->getFrsConnection($server),
//                        $this->accountCandleDaoFactory->create($server),
//                        $this->logger
//                    ))->setFRSServerId($this->frsMap[$server]);
            } else {
                self::$tradingDataGateways[$server] =
                    (new TradeOrderFRSMT5GatewayImpl(
                        $this->factory->getFrsConnection($server),
                        $this->accountCandleDaoFactory->create($server),
                        $this->logger
                    ))->setFRSServerId($this->frsMap[$server]);
            }
        }

        return self::$tradingDataGateways[$server];
    }

    /**
     *
     * @param ServerAwareAccount $acc
     * @return TradeOrderGateway
     */
    public function getForAccount(ServerAwareAccount $acc): TradeOrderGateway
    {
        return $this->getForServer($acc->server());
    }

    public function getOrdersForPeriod(ServerAwareAccount $acc, DateTime $start, DateTime $end)
    {
        return $this->getForAccount($acc)->getOrdersForPeriod($acc->number(), $start, $end);
    }

    public function getOrderHistory(ServerAwareAccount $acc)
    {
        return $this->getForAccount($acc)->getOrderHistory($acc->number());
    }

    public function hasOpenPositions(ServerAwareAccount $acc)
    {
        return $this->getForAccount($acc)->hasOpenPositions($acc->number());
    }

    public function getApplicableSessions(ServerAwareAccount $acc)
    {
        return $this->getForAccount($acc)->getApplicableSessions($acc->number());
    }

    public function getApplicableHolidays(ServerAwareAccount $acc)
    {
        return $this->getForAccount($acc)->getApplicableHolidays($acc->number());
    }

    /**
     * Checks if the given logins had any trading activity since given date
     * Returns array of logins without trade activity
     *
     * @param array $logins
     * @param DateTime $date
     * @return array
     */
    public function getLoginsWithTradingSince(array $logins, DateTime $date) : array
    {
        $result = [];
        foreach (Server::list() as $server) {
            if (!Server::containsLeaders($server)) {
                continue;
            }
            $result = array_merge($result, $this->getForServer($server)->getLoginsWithTradingSince($logins, $date));
        }
        array_walk($result, function (&$item){ $item = intval($item); });
        return $result;
    }
}

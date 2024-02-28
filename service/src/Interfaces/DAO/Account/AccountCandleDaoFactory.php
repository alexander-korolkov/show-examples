<?php

namespace Fxtm\CopyTrading\Interfaces\DAO\Account;

use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\DbConnector\ClickHouseConnector;
use \InvalidArgumentException;


class AccountCandleDaoFactory
{
    /**
     * @var ClickHouseConnector
     */
    private $clickHouseConnector;

    /**
     * @var array
     */
    private $clickHouseMap;



    /**
     * AccountCandleDaoFactory constructor.
     * @param ClickHouseConnector $clickHouseConnector
     * @param array $clickHouseMap
     */
    public function __construct(
        ClickHouseConnector $clickHouseConnector,
        array $clickHouseMap
    ) {
        $this->clickHouseConnector = $clickHouseConnector;
        $this->clickHouseMap = $clickHouseMap;
    }

    /**
     * @param int $server
     * @return AccountCandleDao
     */
    public function create(int $server) : AccountCandleDao
    {
            if(!isset($this->clickHouseMap[$server])) {
                throw new InvalidArgumentException("Unknown server ID");
            }

            if (in_array($server, [Server::ECN_ZERO, Server::ECN, Server::AI_ECN, Server::ADVANTAGE_ECN])) {
                return new AccountCandleDaoMT4ClickHouseImpl(
                    $this->clickHouseConnector->getConnection($server),
                    $this->clickHouseMap[$server]['serverId'],
                    $this->clickHouseMap[$server]['dbName']
                );
            }
            return new AccountCandleDaoMT5ClickHouseImpl(
                $this->clickHouseConnector->getConnection($server),
                $this->clickHouseMap[$server]['serverId'],
                $this->clickHouseMap[$server]['dbName']
            );
    }

}

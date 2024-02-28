<?php

namespace Fxtm\CopyTrading\Domain\Entity\MetaData;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\Persistence\MetaData\ServerIdentification;
use InvalidArgumentException;
use RuntimeException;

class DataSourceFactory
{

    /**
     * @var Connection[]
     */
    private $myConnections      = [];

    /**
     * @var Connection|null
     */
    private $ctConnection       = null;

    /**
     * @var Connection[]
     */
    private $sasConnections     = [];

    /**
     * @var Connection[]
     */
    private $pluginConnections  = [];

    /**
     * @var Connection[]
     */
    private $frsConnections     = [];

    /**
     * @var Connection[]
     */
    private $arsConnections     = [];

    /**
     * @var Connection[]
     */
    private $frsConnectionByServer = [];

    public function addPluginConnection(int $serverId, Connection $connection): void
    {
        if (!in_array($serverId, Server::list())) {
            throw new InvalidArgumentException("Server id {$serverId} is Unknown");
        }
        $this->pluginConnections[$serverId] = $connection;
    }

    public function addMyConnection(string $broker, Connection $connection): void
    {
        if (!in_array($broker, Broker::list())) {
            throw new InvalidArgumentException("Broker {$broker} is Unknown");
        }
        $this->myConnections[$broker] = $connection;
    }

    public function addSasConnection(string $broker, Connection $connection): void
    {
        if (!in_array($broker, Broker::list())) {
            throw new InvalidArgumentException("Broker {$broker} is Unknown");
        }
        $this->sasConnections[$broker] = $connection;
    }

    public function setCtConnection(Connection $connection): void
    {
        $this->ctConnection = $connection;
    }

    public function addFrsConnection(string $broker, int $platform, Connection $connection): void
    {
        if (!in_array($broker, Broker::list())) {
            throw new InvalidArgumentException("Broker {$broker} is Unknown");
        }

        if (!in_array($platform, [ServerIdentification::MT_4, ServerIdentification::MT_5])) {
            throw new InvalidArgumentException("Platform version {$platform} is Unknown");
        }

        if (!isset($this->frsConnections[$broker])) {
            $this->frsConnections[$broker] = [];
        }

        $this->frsConnections[$broker][$platform] = $connection;
    }

    public function addArsConnection(int $serverId, Connection $connection): void
    {
        $this->arsConnections[$serverId] = $connection;
    }

    /**
     * @param int $serverId
     * @param Connection $connection
     * This is correctest way
     */
    public function addFrsConnectionByServer(int $serverId, Connection $connection): void
    {
        $this->frsConnectionByServer[$serverId] = $connection;
    }

    /**
     * @return Connection[]
     */
    public function getAllPluginConnections(): array
    {
        return array_values($this->pluginConnections);
    }

    public function getCTConnection(): Connection
    {
        if ($this->ctConnection == null) {
            throw new RuntimeException("CT connection was not properly configured");
        }
        return $this->ctConnection;
    }

    public function getMyConnection(string $broker): Connection
    {
        if (!isset($this->myConnections[$broker])) {
            throw new RuntimeException("MY connection is not configured for broker {$broker}");
        }
        return $this->myConnections[$broker];
    }

    public function getSasConnection(string $broker): Connection
    {
        if (!isset($this->sasConnections[$broker])) {
            throw new RuntimeException("SAS connection is not configured for broker {$broker}");
        }
        return $this->sasConnections[$broker];
    }

    public function getFrsConnection(int $serverId): Connection
    {
        switch ($serverId) {
            case Server::ECN_ZERO:
            case Server::ECN:
            case Server::ADVANTAGE_ECN:
                $broker = Broker::FXTM;
                $platform = ServerIdentification::MT_4;
                break;
            case Server::AI_ECN:
                $broker = Broker::ALPARI;
                $platform = ServerIdentification::MT_4;
                break;
            case Server::MT5_FXTM:
                $broker = Broker::FXTM;
                $platform = ServerIdentification::MT_5;
                break;
            case Server::MT5_AI_ECN:
                return $this->frsConnectionByServer[$serverId];
            case Server::MT5_AINT:
                $broker = Broker::ALPARI;
                $platform = ServerIdentification::MT_5;
                break;
            default:
                throw new RuntimeException("Invalid server ID {$serverId}");
        }

        if (!isset($this->frsConnections[$broker][$platform])) {
            throw new RuntimeException("FRS connection is not configured for server {$serverId}");
        }
        return $this->frsConnections[$broker][$platform];
    }

    public function getPluginConnection(int $serverId): Connection
    {
        if (!isset($this->pluginConnections[$serverId])) {
            throw new RuntimeException("Plugin connection is not configured for server {$serverId}");
        }
        return $this->pluginConnections[$serverId];
    }

    public function bake(MetaData $metaData): DataSourceComponent
    {
        $broker = $metaData->getBroker();
        if (!in_array($broker, [Broker::FXTM, Broker::ALPARI, Broker::ABY])) {
            throw new InvalidArgumentException("Broker {$broker} is Unknown");
        }

        if (!isset($this->myConnections[$broker])) {
            throw new RuntimeException("MY connection is not configured for broker {$broker}");
        }

        if (!isset($this->sasConnections[$broker])) {
            throw new RuntimeException("SAS connection is not configured for broker {$broker}");
        }

        if ($this->ctConnection == null) {
            throw new RuntimeException("CT connection was not properly configured");
        }

        if (!isset($this->frsConnections[$broker])) {
            throw new RuntimeException("FRS connection is not configured for broker {$broker}");
        }

        $serverId = $metaData->getServerId();
        if (!isset($this->pluginConnections[$serverId])) {
            throw new RuntimeException("Plugin connection is not configured for serverId {$serverId}");
        }

        if (
            in_array($serverId, Server::mt4Servers())
        ) {
            if (!isset($this->arsConnections[$serverId])) {
                throw new RuntimeException("ARS connection is not configured for serverId {$serverId}");
            }

            $arsConnection = $this->arsConnections[$serverId];
        } else if ($metaData->isMigrated()) {
            if (!isset($this->arsConnections[$metaData->getArsMt4ServerId()])) {
                throw new RuntimeException("ARS connection is not configured for serverId {$metaData->getArsMt4ServerId()}");
            }

            $arsConnection = $this->arsConnections[$metaData->getArsMt4ServerId()];
        } else {
            $arsConnection = null;
        }

        $tpVersion = $metaData->getTradingPlatformVersion();
        if (!isset($this->frsConnections[$broker][$tpVersion])) {
            throw new RuntimeException("FRS connection is not configured for trading platform MT{$tpVersion}");
        }

        if ($metaData->isMigrated()) {
            if (!isset($this->frsConnections[$broker][ServerIdentification::MT_4])) {
                throw new RuntimeException("FRS connection is not configured for trading platform MT4}");
            }

            return new DataSourceComponent(
                $this->myConnections[$broker],
                $this->sasConnections[$broker],
                $this->ctConnection,
                $this->pluginConnections[$serverId],
                $this->frsConnections[$broker][ServerIdentification::MT_4],
                $this->frsConnections[$broker][$tpVersion],
                $arsConnection
            );
        }

        return new DataSourceComponent(
            $this->myConnections[$broker],
            $this->sasConnections[$broker],
            $this->ctConnection,
            $this->pluginConnections[$serverId],
            null,
            $this->frsConnections[$broker][$tpVersion],
            $arsConnection
        );
    }

    public function getArsConnection(int $serverId): Connection
    {
        if(!isset($this->arsConnections[$serverId])) {
            throw new RuntimeException("ARS connection is not configured for server {$serverId}");
        }
        return $this->arsConnections[$serverId];
    }
}
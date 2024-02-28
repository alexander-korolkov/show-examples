<?php

namespace Fxtm\CopyTrading\Interfaces\DbConnector;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\Adapter\DbConnection\ConnectionHelper;
use InvalidArgumentException;
use PDO;

class FrsConnector
{
    /**
     * @var array
     */
    private $connectionsFactory = [];

    /**
     * @var array
     */
    private $connected = [];

    /**
     * @var DatabaseConnector
     */
    private static $instance;

    private function __clone()
    {
    }

    /**
     * FrsConnector constructor.
     */
    private function __construct()
    {
        $this->connectionsFactory = [
            Server::ECN_ZERO => false,
            Server::ECN => false,
            Server::ADVANTAGE_ECN => false,
            Server::AI_ECN => false,
            Server::MT5_FXTM => false,
            Server::MT5_AINT => false,
        ];
    }

    public function setConnection(int $serverId, Connection $dbalConnection): void
    {
        if (!isset($this->connectionsFactory[$serverId])) {
            throw new InvalidArgumentException("Invalid data source ID");
        }

        // TODO Remove reconnection
        // had to re-configure connection in order to not to broke interface
        // (have to re-do this with normal solution)
        $params = $dbalConnection->getParams();

        $this->connectionsFactory[$serverId] = $this->connectToFrs(
            [
                'db_host' => $params['host'],
                'db_port' => $params['port'] ?? 3306,
                'db_name' => $dbalConnection->getDatabase(),
                'db_user' => $params['user'],
                'db_password' => $params['password'],
            ],
            $serverId
        );
    }

    /**
     * Makes several tries to connect to frs db by given params
     *
     * @param array $config
     * @param int $serverId
     * @return callback
     */
    private function connectToFrs(array $config, int $serverId)
    {
        $params = [
            'db_host' => $config['db_host'],
            'db_port' => $config['db_port'],
            'db_name' => $config['db_name'],
            'db_user' => $config['db_user'],
            'db_password' => $config['db_password'],
        ];

        return function () use ($params) {
            return ConnectionHelper::connectWithAttempts(
                5,
                "mysql:host={$params['db_host']};dbname={$params['db_name']};port={$params['db_port']}",
                $params['db_user'],
                $params['db_password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        };
    }

    /**
     * @return FrsConnector
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns frs connection for given server
     *
     * @param int $serverId
     * @return PDO
     */
    public function getConnection(int $serverId): PDO
    {
        if (!isset($this->connected[$serverId])) {
            $this->connected[$serverId] = $this->connectionsFactory[$serverId]();
        }

        return $this->connected[$serverId];
    }
}

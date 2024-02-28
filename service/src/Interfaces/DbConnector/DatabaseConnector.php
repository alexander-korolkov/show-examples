<?php

namespace Fxtm\CopyTrading\Interfaces\DbConnector;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\Adapter\DbConnection\ConnectionHelper;
use InvalidArgumentException;
use PDO;

class DatabaseConnector
{
    private const KEY_BROKER = 'broker';
    private const KEY_ALIAS = 'alias';

    public const DATABASE_ALIAS_MY = 'my';
    public const DATABASE_ALIAS_SAS = 'sas';
    public const DATABASE_ALIAS_ARS_ECN = 'ars_ecn';
    public const DATABASE_ALIAS_ARS_ADVANTAGE_ECN = 'ars_advantage_ecn';
    public const DATABASE_ALIAS_ARS_ECN_ZERO = 'ars_ecn0';
    public const DATABASE_ALIAS_PLUGIN_ECN = 'plugin_ecn';
    public const DATABASE_ALIAS_PLUGIN_ECN_ZERO = 'plugin_ecn0';
    public const DATABASE_ALIAS_PLUGIN_MT5 = 'plugin_mt5';

    /**
     * @var array
     */
    private $arsServers = [
        //FXTM
        Server::ECN_ZERO => [
            self::KEY_BROKER => Broker::FXTM,
            self::KEY_ALIAS => self::DATABASE_ALIAS_ARS_ECN_ZERO
        ],
        Server::ECN => [
            self::KEY_BROKER => Broker::FXTM,
            self::KEY_ALIAS => self::DATABASE_ALIAS_ARS_ECN
        ],
        Server::ADVANTAGE_ECN => [
            self::KEY_BROKER => Broker::FXTM,
            self::KEY_ALIAS => self::DATABASE_ALIAS_ARS_ADVANTAGE_ECN
        ],
        //ALPARI
        Server::AI_ECN => [
            self::KEY_BROKER => Broker::ALPARI,
            self::KEY_ALIAS => self::DATABASE_ALIAS_ARS_ECN
        ],
    ];

    /**
     * @var array
     */
    private $pluginServers = [
        Server::ECN_ZERO => [
            self::KEY_BROKER => Broker::FXTM,
            self::KEY_ALIAS => self::DATABASE_ALIAS_PLUGIN_ECN_ZERO
        ],
        Server::ECN => [
            self::KEY_BROKER => Broker::FXTM,
            self::KEY_ALIAS => self::DATABASE_ALIAS_PLUGIN_ECN
        ],
        Server::AI_ECN => [
            self::KEY_BROKER => Broker::ALPARI,
            self::KEY_ALIAS => self::DATABASE_ALIAS_PLUGIN_ECN
        ],
        Server::MT5_FXTM => [
            self::KEY_BROKER => Broker::FXTM,
            self::KEY_ALIAS => self::DATABASE_ALIAS_PLUGIN_MT5
        ],
        Server::MT5_AINT => [
            self::KEY_BROKER => Broker::ALPARI,
            self::KEY_ALIAS => self::DATABASE_ALIAS_PLUGIN_MT5
        ],
        Server::ADVANTAGE_ECN => [
            self::KEY_BROKER => Broker::FXTM,
            self::KEY_ALIAS => self::DATABASE_ALIAS_PLUGIN_ECN
        ],
    ];

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
     * DatabaseConnector constructor.
     */
    private function __construct()
    {
        /*
         * Initial population of the factory array; references to PDO objects will be added by
         * DI and configured in services.yaml
         * Used "false" value because following isset(...) checks are working through the ass
         */
        $this->connectionsFactory[Broker::FXTM] = [
            self::DATABASE_ALIAS_MY                 => false,
            self::DATABASE_ALIAS_SAS                => false,
            self::DATABASE_ALIAS_ARS_ECN            => false,
            self::DATABASE_ALIAS_ARS_ECN_ZERO       => false,
            self::DATABASE_ALIAS_ARS_ADVANTAGE_ECN  => false,
            self::DATABASE_ALIAS_PLUGIN_ECN         => false,
            self::DATABASE_ALIAS_PLUGIN_ECN_ZERO    => false,
            self::DATABASE_ALIAS_PLUGIN_MT5         => false,
        ];

        $this->connectionsFactory[Broker::ALPARI] = [
            self::DATABASE_ALIAS_MY             => false,
            self::DATABASE_ALIAS_SAS            => false,
            self::DATABASE_ALIAS_ARS_ECN        => false,
            self::DATABASE_ALIAS_PLUGIN_ECN     => false,
            self::DATABASE_ALIAS_PLUGIN_MT5     => false,
        ];
    }

    public function setConnection(string $brokerId, string $dbAlias, Connection $dbalConnection): void
    {
        if (!isset($this->connectionsFactory[$brokerId][$dbAlias])) {
            throw new InvalidArgumentException("Invalid data source ID");
        }

        // TODO Remove reconnection
        // had to re-configure connection in order to not to broke interface
        // (have to re-do this with normal solution)
        $params = $dbalConnection->getParams();

        $this->connectionsFactory[$brokerId][$dbAlias] = $this->connectToDb(
            [
                "{$brokerId}.{$dbAlias}.db_host" => $params['host'],
                "{$brokerId}.{$dbAlias}.db_port" => $params['port'] ?? 3306,
                "{$brokerId}.{$dbAlias}.db_name" => $dbalConnection->getDatabase(),
                "{$brokerId}.{$dbAlias}.db_user" => $params['user'],
                "{$brokerId}.{$dbAlias}.db_password" => $params['password'],
            ],
            $brokerId,
            $dbAlias
        );
    }

    /**
     * Makes several tries to connect to db by given params
     *
     * @param array $config
     * @param string $broker
     * @param string $dbAlias
     * @return callback
     */
    private function connectToDb(array $config, $broker, $dbAlias)
    {
        $params = [
            'db_host' => $config["$broker.$dbAlias.db_host"],
            'db_port' => $config["$broker.$dbAlias.db_port"] ?? 3306,
            'db_name' => $config["$broker.$dbAlias.db_name"],
            'db_user' => $config["$broker.$dbAlias.db_user"],
            'db_password' => $config["$broker.$dbAlias.db_password"],
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
     * @return DatabaseConnector
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns connection for given broker to database with given alias
     *
     * @param string $broker
     * @param string $dbAlias
     * @return PDO
     */
    public function getConnection(string $broker, string $dbAlias): PDO
    {
        if (!isset($this->connected[$broker][$dbAlias])) {
            $this->connected[$broker][$dbAlias] = $this->connectionsFactory[$broker][$dbAlias]();
        }

        return $this->connected[$broker][$dbAlias];
    }

    /**
     * Returns NEW connection for given broker to database with given alias
     *
     * @param string $broker
     * @param string $dbAlias
     * @return PDO
     */
    public function getNewConnection(string $broker, string $dbAlias): PDO
    {
        return $this->connectionsFactory[$broker][$dbAlias]();
    }

    /**
     * Returns connection for one of ars servers by its unique id
     *
     * @param int $server
     * @return PDO
     */
    public function getArsConnectionForServer(int $server): PDO
    {
        $arsServer = $this->arsServers[$server];
        if (!$arsServer) {
            throw new InvalidArgumentException("Invalid server ID for ARS connector : $server");
        }

        return $this->getConnection($arsServer[self::KEY_BROKER], $arsServer[self::KEY_ALIAS]);
    }

    /**
     * Returns connection for one of plugin servers by its unique id
     *
     * @param int $server
     * @return PDO
     */
    public function getPluginConnectionForServer(int $server): PDO
    {
        $pluginServer = $this->pluginServers[$server];

        return $this->getConnection($pluginServer[self::KEY_BROKER], $pluginServer[self::KEY_ALIAS]);
    }
}

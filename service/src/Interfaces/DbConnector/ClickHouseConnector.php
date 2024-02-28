<?php

namespace Fxtm\CopyTrading\Interfaces\DbConnector;

use ClickHouseDB\Client;
use \InvalidArgumentException;

class ClickHouseConnector
{
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
    }

    public function setConnection(int $serverId, string $host, string $port, string $user, string $pass) : void
    {
        $this->connected[$serverId] = new Client([
            'host' => $host,
            'port' => $port,
            'username' => $user,
            'password' => $pass,
            'readonly' => true,
        ]);
    }

    /**
     * @return ClickHouseConnector
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns clickhouse connection for given server
     *
     * @param int $serverId
     * @return Client
     */
    public function getConnection(int $serverId) : Client
    {
        if (!isset($this->connected[$serverId])) {
            throw new InvalidArgumentException('Unknown server ID ' . $serverId);
        }

        return $this->connected[$serverId];
    }
}

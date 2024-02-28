<?php

namespace Fxtm\CopyTrading\Application\Common;

use Doctrine\DBAL\Connection;

class WorkflowLockerFactory
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $memcacheServerIp;

    /**
     * @var string
     */
    private $memcacheServerPort;

    /**
     * WorkflowLockerFactory constructor.
     * @param Connection $connection
     * @param string $memcacheServerIp
     * @param string $memcacheServerPort
     */
    public function __construct(Connection $connection, string $memcacheServerIp, string $memcacheServerPort)
    {
        $this->connection = $connection;
        $this->memcacheServerIp = $memcacheServerIp;
        $this->memcacheServerPort = $memcacheServerPort;
    }

    public function __invoke()
    {
        return new CompositeLocker([
            new MysqlLocker($this->connection),
//            new MemcacheLocker($this->memcacheServerIp, $this->memcacheServerPort)
        ]);
    }
}

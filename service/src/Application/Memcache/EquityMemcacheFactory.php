<?php

namespace Fxtm\CopyTrading\Application\Memcache;

use Memcache;

class EquityMemcacheFactory
{
    /**
     * @var string
     */
    private $memcacheRun;

    /**
     * @var string
     */
    private $serverIp;

    /**
     * @var string
     */
    private $serverPort;

    public function __construct(
        string $memcacheRun,
        string $serverIp,
        string $serverPort
    )
    {
        $this->memcacheRun = $memcacheRun;
        $this->serverIp = $serverIp;
        $this->serverPort = $serverPort;
    }

    public function createEquityMemcache(): ?Memcache
    {
        if ($this->memcacheRun) {
            $memcache = new Memcache();
            $memcache->addserver($this->serverIp, $this->serverPort);
            return $memcache;
        }

        return null;
    }
}

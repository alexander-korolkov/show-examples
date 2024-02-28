<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Plugin;

use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;


class PluginGatewayManagerImpl implements PluginGatewayManager
{
    private static $pluginGateways = [];

    /**
     * @var DataSourceFactory
     */
    private $factory;

    public function __construct(DataSourceFactory $factory)
    {
        $this->factory = $factory;
    }

    public function getForAccount(ServerAwareAccount $account)
    {
        return $this->getForServer($account->server());
    }

    public function getForServer($server)
    {
        if (empty(self::$pluginGateways[$server])) {
            self::$pluginGateways[$server] = new PluginGatewayImpl($this->factory->getPluginConnection($server));
        }
        return self::$pluginGateways[$server];
    }
}

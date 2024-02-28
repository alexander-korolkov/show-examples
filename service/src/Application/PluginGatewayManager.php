<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;

interface PluginGatewayManager
{
    /**
     * @param ServerAwareAccount $account
     * @return PluginGateway gateway
     */
    public function getForAccount(ServerAwareAccount $account);

    /**
     * @param int $server
     * @return PluginGateway gateway
     */
    public function getForServer($server);
}

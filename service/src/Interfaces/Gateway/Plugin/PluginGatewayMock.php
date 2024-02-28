<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Plugin;

use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class PluginGatewayMock implements PluginGateway
{
    /**
     * @param AccountNumber $accNo
     * @param int $corrId
     * @param int $msgType
     * @param int $msgPayload
     * @return int message ID
     */
    public function sendMessage(AccountNumber $accNo, $corrId, $msgType, $msgPayload)
    {
        return rand();
    }

    /**
     * @param int $msgId
     * @return boolean
     * @throws PluginException
     */
    public function isMessageAcknowledged($msgId)
    {
        return true;
    }
}

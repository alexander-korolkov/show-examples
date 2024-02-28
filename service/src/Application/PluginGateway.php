<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface PluginGateway
{
    const LEADER_DEPOSIT        = "leader_deposit";
    const LEADER_WITHDRAWAL     = "leader_withdrawal";
    const LEADER_COPIED         = "leader_copied";
    const LEADER_COPIED_NOT     = "leader_copied_not";
    const LEADER_UNLOCK         = "leader_unlock";
    const LEADER_REFRESH        = "leader_refresh";
    const FOLLOWER_DEPOSIT      = "follower_deposit";
    const FOLLOWER_WITHDRAWAL   = "follower_withdrawal";
    const FOLLOWER_COMMISSION   = "follower_commission";
    const FOLLOWER_STOPLOSS     = "follower_stoploss";
    const FOLLOWER_COPYING      = "follower_copying";
    const FOLLOWER_COPYING_ALL  = "follower_copying_all";
    const FOLLOWER_COEF         = "follower_coef";

    public function sendMessage(AccountNumber $accNo, $corrId, $msgType, $msgPayload);

    /**
     *
     * @param int $msgId
     * @return boolean
     * @throws GatewayException
     */
    public function isMessageAcknowledged($msgId);

    public function acknowledgeMessage($msgId);

    public function messageFailed($msgId, $comment);

    public function messageCanceled($msgId);

    public function multipleMessagesCancelled(array $ids): void;

    public function getMessageResult($msgId);

    /**
     * Retrieve message by Id.
     *
     * @param $msgId
     * @return mixed
     */
    public function getMessageById($msgId);
}

<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Plugin;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use PDO;

class PluginGatewayImpl implements PluginGateway
{
    private $dbConn = null;

    const INIT_BY_ME     = 1;
    const INIT_BY_PLUGIN = 2;

    const STATUS_ME_SENT      = 1;
    const STATUS_PLUGIN_ACKED = 2;
    const STATUS_ME_ACKED     = 3;
    const STATUS_CANCELLED    = 4;
    const STATUS_IN_PROGRESS  = 5;

    const RESULT_SUCCESS = 0;
    const RESULT_ERROR = 999;

    public static $msgTypes = [
        PluginGateway::LEADER_DEPOSIT       => 1,
        PluginGateway::FOLLOWER_DEPOSIT     => 2,
        PluginGateway::FOLLOWER_STOPLOSS    => 3,
        PluginGateway::LEADER_WITHDRAWAL    => 4,
        PluginGateway::FOLLOWER_WITHDRAWAL  => 5,
        PluginGateway::FOLLOWER_COPYING     => 6,
        PluginGateway::FOLLOWER_COEF        => 7,
        PluginGateway::FOLLOWER_COPYING_ALL => 8,
        PluginGateway::LEADER_COPIED        => 9,
        PluginGateway::LEADER_COPIED_NOT    => 10,
        PluginGateway::LEADER_UNLOCK        => 20,
        PluginGateway::LEADER_REFRESH       => 21,
    ];

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    /**
     * @param AccountNumber $accNo
     * @param int $corrId
     * @param int $msgType
     * @param int $msgPayload
     * @return int message ID
     */
    public function sendMessage(AccountNumber $accNo, $corrId, $msgType, $msgPayload)
    {
        $stmt = $this->dbConn->prepare("
            INSERT INTO `plugin_msg_queue` (
                `corr_id`,
                `init_by`,
                `msg_type`,
                `acc_no`,
                `payload`,
                `received_at`,
                `status`
            ) VALUES (
                :corr_id,
                :init_by,
                :msg_type,
                :acc_no,
                :payload,
                :received_at,
                :status
            )
        ");
        $stmt->execute([
            "corr_id"     => $corrId,
            "init_by"     => self::INIT_BY_ME,
            "msg_type"    => self::$msgTypes[$msgType],
            "acc_no"      => $accNo->value(),
            "payload"     => $msgPayload,
            "received_at" => DateTime::NOW()->__toString(),
            "status"      => self::STATUS_ME_SENT,

        ]);
        return intval($this->dbConn->lastInsertId());
    }

    /**
     * @param int $msgId
     * @return boolean
     * @throws PluginException
     */
    public function isMessageAcknowledged($msgId)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `plugin_msg_queue` WHERE `id` = ? AND `status` IN (?, ?)");
        $stmt->execute([$msgId, self::STATUS_PLUGIN_ACKED, self::STATUS_ME_ACKED]);
        if (empty($msg = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return false;
        }
        if ($msg["status"] == self::STATUS_PLUGIN_ACKED) {
            $this->acknowledgeMessage($msgId);
        }
        if ($msg["result"] > self::RESULT_SUCCESS) {
            throw new PluginException($msg["result"], $msg["comment"]);
        }
        return true;
    }

    public function acknowledgeMessage($msgId)
    {
        $stmt = $this->dbConn->prepare("UPDATE `plugin_msg_queue` SET `acked_at` = NOW(), `status` = ? WHERE `id` = ? AND `status` = ?");
        $stmt->execute([self::STATUS_ME_ACKED, $msgId, self::STATUS_PLUGIN_ACKED]);
    }

    public function messageFailed($msgId, $comment)
    {
        $stmt = $this->dbConn->prepare("UPDATE `plugin_msg_queue` SET `result` = ? , `comment` = ? WHERE `id` = ?");
        $stmt->execute([self::RESULT_ERROR, $comment, $msgId]);
    }

    public function messageCanceled($msgId)
    {
        $stmt = $this->dbConn->prepare("UPDATE `plugin_msg_queue` SET `status` = ? WHERE `id` = ?");
        $stmt->execute([self::STATUS_CANCELLED, $msgId]);
    }

    /**
     * @param array $ids
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function multipleMessagesCancelled(array $ids): void
    {
        $sql = 'UPDATE `plugin_msg_queue` SET `status` = :status_id WHERE `id` IN (' . implode(', ', $ids). ')';
        $stmt = $this->dbConn->prepare($sql);
        $stmt->executeStatement([
            ':status_id' => self::STATUS_CANCELLED,
        ]);
    }

    public function getMessageResult($msgId)
    {
        $stmt = $this->dbConn->prepare("SELECT result FROM `plugin_msg_queue` WHERE `id` = ?");
        $stmt->execute([$msgId]);
        $result = $stmt->fetchColumn();
        if (false === $result) {
            throw new PluginException(0, "Message #{$msgId} doesn't exist");
        }
        return $result;
    }

    public function getMessageById($msgId)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `plugin_msg_queue` WHERE `id` = ?");
        $stmt->execute([$msgId]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return !empty($result[0]) ? $result[0] : $result;
    }
}

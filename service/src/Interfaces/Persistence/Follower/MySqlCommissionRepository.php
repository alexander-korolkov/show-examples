<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Follower;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Model\Follower\Commission;
use Fxtm\CopyTrading\Domain\Model\Follower\CommissionRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use PDO;

class MySqlCommissionRepository implements CommissionRepository
{
    private $dbConn = null;

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function store(Commission $commission)
    {
        $commissionStmt = $this->dbConn->prepare("
            REPLACE INTO `commission` VALUES (
                :id,
                :workflow_id,
                :trans_id,
                :broker,
                :acc_no,
                :created_at,
                :amount,
                :type,
                :prev_equity,
                :prev_fee_level,
                :comment
            )
        ");

        $params = $commission->toArray();
        $commissionStmt->execute($params);
    }

    public function findByWorkflowId($id)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `commission` WHERE `workflow_id` = ?");
        $stmt->execute([$id]);
        if (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return $this->mapFromArray($row);
        }
        return null;
    }

    public function findByTransId($id)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `commission` WHERE `trans_id` = ?");
        $stmt->execute([$id]);
        if (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return $this->mapFromArray($row);
        }
        return null;
    }

    public function findByAccountNumberForPeriod(AccountNumber $accNo, $start, $end)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `commission` WHERE `acc_no` = ? AND `created_at` > ? AND `created_at` <= ? AND `amount` > 0");
        $stmt->execute([$accNo->value(), $start, $end]);
        $commissions = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $commissions[] = $this->mapFromArray($row);
        }
        return $commissions;
    }

    private function mapFromArray(array $array)
    {
        $comm = unserialize(sprintf('O:%d:"%s":0:{}', strlen(Commission::CLASS), Commission::CLASS));
        $comm->fromArray($array);
        return $comm;
    }

    /**
     * Returns true if given account has only one END_OF_INTERVAL type commission
     *
     * @param AccountNumber $accNo
     * @return bool
     */
    public function isFirstPayout(AccountNumber $accNo): bool
    {
        $stmt = $this->dbConn->prepare("
          SELECT COUNT(id) FROM `commission` 
          WHERE `acc_no` = ? AND `type` = ?");
        $stmt->execute([$accNo->value(), Commission::TYPE_PERIODICAL]);
        $result = $stmt->fetchColumn();

        return $result <= 1;
    }

    /**
     * Returns latest fee of type PERIODICAL_PAYOUT or ACCOUNT_CLOSING
     *
     * @param AccountNumber $accNo
     * @return array
     */
    public function getLatestForStatement(AccountNumber $accNo)
    {
        $types = implode(',', [Commission::TYPE_PERIODICAL, Commission::TYPE_CLOSE_ACCOUNT]);
        $stmt = $this->dbConn->prepare("
          SELECT * FROM `commission` 
          WHERE `acc_no` = ? AND `type` IN ({$types})
          ORDER BY `created_at` DESC LIMIT 1");
        $stmt->execute([$accNo->value()]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns latest fee of type PERIODICAL_PAYOUT if it exists
     *
     * @param AccountNumber $accNo
     * @return array | null
     */
    public function getLastPayout(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
          SELECT * FROM `commission` 
          WHERE `acc_no` = ? AND `type` = ?
          ORDER BY `created_at` DESC LIMIT 1");
        $stmt->execute([$accNo->value(), Commission::TYPE_PERIODICAL]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns previous before latest fee of type PERIODICAL_PAYOUT if it exists
     *
     * @param AccountNumber $accNo
     * @return array | null
     */
    public function getPreviousPayout(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
          SELECT * FROM `commission` 
          WHERE `acc_no` = ? AND `type` = ?
          ORDER BY `created_at` DESC LIMIT 1 OFFSET 1");
        $stmt->execute([$accNo->value(), Commission::TYPE_PERIODICAL]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

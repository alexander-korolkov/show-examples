<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Follower;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\StopLossHistory;
use Fxtm\CopyTrading\Domain\Model\Follower\StopLossRecord;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use PDO;

class MySqlStopLossHistory implements StopLossHistory
{
    private $dbConn = null;

    public function __construct(PDO $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function getLastStopLossRecord(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
            SELECT fsh.*, fa.acc_curr
            FROM follower_stoploss_history fsh
            JOIN follower_accounts fa USING (acc_no)
            WHERE fsh.acc_no = ?
            ORDER BY fsh.changed_at DESC
            LIMIT 1
        ");
        $stmt->execute([$accNo->value()]);
        if (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return new StopLossRecord(
                new AccountNumber($row["acc_no"]),
                $row["stoploss_level"],
                new Money($row["stoploss_equity"], Currency::forCode($row["acc_curr"])),
                $row["stoploss_action"],
                DateTime::of($row["changed_at"])
            );
        }
    }
}

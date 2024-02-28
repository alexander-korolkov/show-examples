<?php

namespace Fxtm\CopyTrading\Interfaces\Statistics;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\StatisticsService;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;

class StatisticsServiceImpl implements StatisticsService
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TradeOrderGatewayFacade
     */
    private $orderGateway;

    public function __construct(Connection $connection, TradeOrderGatewayFacade $orderGateway)
    {
        $this->connection = $connection;
        $this->orderGateway = $orderGateway;
    }

    public function importEquityStatistics(ServerAwareAccount $acc)
    {
        $stmt = $this->connection->prepare("INSERT INTO equities (acc_no, date_time, equity, in_out) VALUES (:acc_no, :date_time, :equity, :in_out)");

        $equity = 0;
        $nextHour = null;
        $buySellOrders = 0;
        foreach ($this->orderGateway->getOrderHistory($acc) as $order) {
            $dt = DateTime::of($order["date_time"]);
            if (is_null($nextHour)) {
                $nextHour = $dt->nextHour();
            }

            if ($dt > $nextHour && $buySellOrders > 0) {
                $stmt->execute([
                    "acc_no"    => $order["acc_no"],
                    "date_time" => $nextHour,
                    "equity"    => $equity,
                    "in_out"    => 0.0000,
                ]);
                $buySellOrders = 0;
            }

            $equity += $order["equity_diff"];

            if (in_array($order["type"], [2, 6, 7])) {
                $stmt->execute([
                    "acc_no"    => $order["acc_no"],
                    "date_time" => $order["date_time"],
                    "equity"    => $equity,
                    "in_out"    => $order["equity_diff"],
                ]);
            } else {
                $buySellOrders++;
            }

            $nextHour = $dt->nextHour();
        }

        $this->setNetBalance($acc->number(), $equity);

        // last hour
        if ($nextHour < DateTime::NOW()->currHour() && $buySellOrders > 0) {
            $stmt->execute([
                "acc_no"    => $order["acc_no"],
                "date_time" => $nextHour,
                "equity"    => $equity,
                "in_out"    => 0.0000,
            ]);
        }
    }

    public function getFirstDepositDatetime(AccountNumber $accNo)
    {
        if (empty($dt = $this->connection->query("SELECT date_time FROM equities WHERE acc_no = {$accNo} ORDER BY date_time ASC LIMIT 1")->fetchColumn())) {
            return null;
        }
        return DateTime::of($dt);
    }

    public function getLeaderEquityStatistics(AccountNumber $accNo)
    {
        return $this->connection->query("SELECT * FROM leader_equity_stats WHERE acc_no = {$accNo}")->fetch(\PDO::FETCH_ASSOC);
    }

    public function setNetBalance($accNo, $net)
    {
        $stmt = $this->connection->prepare("UPDATE leader_accounts SET balance = :net WHERE acc_no = :acc_no");
        $stmt->execute([
            "net"    => $net,
            "acc_no" => $accNo,
        ]);
    }
}

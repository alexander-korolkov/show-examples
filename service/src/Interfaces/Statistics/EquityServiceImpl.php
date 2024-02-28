<?php

namespace Fxtm\CopyTrading\Interfaces\Statistics;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Application\Utils\FloatUtils;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use PDO;
use Psr\Log\LoggerInterface;

class EquityServiceImpl implements EquityService
{
    private $dbConn = null;

    /**
     * @var TradeOrderGatewayFacade
     */
    private $tradeOrderGatewayFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Connection $dbConn,
        TradeOrderGatewayFacade $tradeOrderGatewayFacade,
        LoggerInterface $logger
    ) {
        $this->dbConn = $dbConn;
        $this->tradeOrderGatewayFacade = $tradeOrderGatewayFacade;
        $this->logger = $logger;
    }

    public function saveTransactionEquityChange(AccountNumber $accNo, Money $equity, Money $transAmt, $order = null, $dateTime = null)
    {
        // Check if order already exists in db
        // Query SELECT EXISTS(...) should work much faster
        if($order) {
            $statement = $this->dbConn->prepare("SELECT EXISTS(SELECT * FROM equities_orders WHERE order_id = ?)");
            $statement->execute([$order]);
            if(intval($statement->fetchColumn()) != 0) {
                $this->logger->warning(sprintf('EquityService: Order already handled: %s', $order));
                return;
            }
        }

        $this->dbConn->beginTransaction();
        try {
            $dateTime = $dateTime ? $dateTime : DateTime::NOW();

            $sql = 'INSERT INTO equities (acc_no, date_time, equity, in_out) VALUES (:acc_no, :date_time, :equity, :in_out)';
            $params = [
                "acc_no" => $accNo,
                "date_time" => $dateTime,
                "equity" => $equity->amount(),
                "in_out" => $transAmt->amount(),
            ];
            $stmt = $this->dbConn->prepare($sql);
            $stmt->execute($params);

            if ($order) {

                $sql = 'INSERT INTO equities_orders (equity_id, order_id) VALUES (:equity_id, :order_id)';

                $params = [
                    'equity_id' => $this->dbConn->lastInsertId(),
                    'order_id' => $order,
                ];

                $stmt = $this->dbConn->prepare($sql);
                $stmt->execute($params);
            }

            $this->dbConn->commit();
        }
        catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'EquityService: Exception %s with message %s has been occurred, Stack Trace: %s',
                get_class($exception),
                $exception->getMessage(),
                $exception->getTraceAsString()
            ));
            $this->dbConn->rollBack();
        }
    }

    public function fixTransactionEquityChange(AccountNumber $accNo, Money $equity, Money $transAmt, $order = null, $dateTime = null)
    {
        // Check if order already exists in db
        // Query SELECT EXISTS(...) should work much faster
        if ($order) {
            $statement = $this->dbConn->prepare("SELECT * FROM equities_orders WHERE order_id = ?");
            $statement->execute([$order]);
            $row = $statement->fetch();
            if ($row) {
                $statement = $this->dbConn->prepare('SELECT id, equity FROM equities WHERE  id = ?');
                $statement->execute([$row['equity_id']]);
                $row = $statement->fetch();
                if (intval($row['equity'] * 100.0) != intval($equity->amount() * 100.0)) {
                    $statement = $this->dbConn->prepare('UPDATE equities SET equity = ? WHERE id = ?');
                    $statement->execute([$equity->amount(), $row['id']]);
                }
                return;
            }
        }
        try {
            $dateTime = $dateTime ? $dateTime : DateTime::NOW();
            $sql = 'INSERT INTO equities (acc_no, date_time, equity, in_out) VALUES (:acc_no, :date_time, :equity, :in_out);';
            $params = [
                "acc_no" => $accNo,
                "date_time" => $dateTime,
                "equity" => $equity->amount(),
                "in_out" => $transAmt->amount(),
            ];
            if ($order) {
                $sql .= 'INSERT INTO equities_orders (equity_id, order_id) VALUES (LAST_INSERT_ID(), :order_id);';
                $params['order_id'] = $order;
            }
            $stmt = $this->dbConn->prepare($sql);
            $stmt->execute($params);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'EquityService: Exception %s with message %s has been occurred, Stack Trace: %s',
                get_class($exception),
                $exception->getMessage(),
                $exception->getTraceAsString()
            ));
        }
    }

    public function getAccountEquity(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM equities WHERE acc_no = ? ORDER BY date_time ASC");
        $stmt->execute([$accNo]);
        if (empty($r = $stmt->fetchAll(PDO::FETCH_ASSOC))) {
            return [];
        }

        return $this->calculateUnitPrices($accNo->value(), $r);
    }

    /**
     * @param array $accountNumbers
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getLastEquitiesByAccountsNumbersFromLocalDb(array $accountNumbers): array
    {
        $sql = "SELECT eq.acc_no, eq.equity FROM (
                    SELECT acc_no, MAX(date_time) AS max_date_time from equities WHERE acc_no IN (" . implode(',', $accountNumbers). ") GROUP BY acc_no
                ) AS _tmp
                INNER JOIN equities as eq WHERE eq.acc_no = _tmp.acc_no AND eq.date_time = _tmp.max_date_time";

        $stmt = $this->dbConn->prepare($sql);
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        $equities = [];
        foreach ($rows as $row) {
            $equities[$row['acc_no']] = $row['equity'];
        }

        return $equities;
    }

    /**
     * Method calculates unit prices for given equityData
     *
     * @param $accountNumber
     * @param array $equityData
     * @return array
     */
    private function calculateUnitPrices($accountNumber, array $equityData) : array
    {
        $equities = [];

        // 0th record
        $equities[] = [
            "acc_no"     => $accountNumber,
            "date_time"  => DateTime::of($equityData[0]["date_time"])->currHour()->__toString(),
            "equity"     => 0.0000,
            "in_out"     => 0.0000,
            "unit_price" => 1.0000,
        ];

        for ($i = 0; $i < sizeof($equityData); $i++) {
            if ($i == 0) {
                $up = 1.0000;
            } else if ($equityData[$i - 1]["equity"] == 0) {
                if ($equityData[$i - 1]["in_out"] < 0) { // all funds withdrawn
                    $up = $equityData[$i - 1]["unit_price"];
                } else { // all funds lost
                    $up = ($equityData[$i]["equity"] - $equityData[$i]["in_out"]) * $equityData[$i - 1]["unit_price"] / 0.0001;
                }
            } else {
                $up = ($equityData[$i]["equity"] - $equityData[$i]["in_out"]) *
                    $equityData[$i - 1]["unit_price"] / $equityData[$i - 1]["equity"];
            }
            if (is_nan($up) || is_infinite($up) || $up < 0.0001) {
                $up = 0.0001;
            }
            $equityData[$i]["unit_price"] = round($up, 4);
            $equities[] = $equityData[$i];
        }

        return $equities;
    }

    public function getAccountDailyEquity(AccountNumber $accNo)
    {
        $daily = [];
        $all = $this->getAccountEquity($accNo);
        if (empty($all)) {
            return $daily;
        }

        $daily[] = $all[0]; // the first element
        $nextDay = DateTime::of($all[0]["date_time"])->nextDay();
        for ($i = 1; $i < sizeof($all) - 1; $i++) {
            $dt = DateTime::of($all[$i]["date_time"]);
            if (in_array($dt->getWeekdayNumber(), range(2, 6)) && $dt >= $nextDay) {
                $daily[] = $all[$i];
            }
            $nextDay = $dt->nextDay();
        }
        $daily[] = $all[sizeof($all) - 1]; // the last element
        return $daily;
    }

    /**
     * Returns equity changes for given accounts
     * from the MT Servers using WebGate class
     *
     * @param array $accounts
     * @param DateTime $onDatetime
     * @return array
     */
    public function getAccountsEquityFormWebGate(array $accounts, DateTime $onDatetime): array
    {
        $equities = [];
        foreach ($accounts as $server => $accToType) {
            $result = $this->tradeOrderGatewayFacade
                ->getForServer($server)
                ->getBulkAccountEquities(array_keys($accToType), $onDatetime);

            foreach ($result as $login => $equity) {
                $equities[$login] = $equity;
            }
        }
        return $equities;
    }

    /**
     * Returns equities for given accounts
     * which will be used for statistics calculations
     *
     * @param array $accountNumbers
     * @return array
     */
    public function getEquityForStatistics(array $accountNumbers): array
    {
        $stmt = $this->dbConn->query(
            'SELECT equities.acc_no, equities.* FROM equities WHERE acc_no IN (' . implode(',', $accountNumbers) . ') ORDER BY acc_no ASC, date_time ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);

        $equities = [];
        foreach ($rows as $accountNumber => $equityData) {
            $equities[$accountNumber] = $this->calculateUnitPrices($accountNumber, $equityData);
        }

        return $equities;
    }

    /**
     * Returns first equity row of account after he deposited at least minimum equity
     *
     * @param AccountNumber $accNo
     * @return array
     */
    public function getFirstActiveEquity(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
          SELECT eq.*
          FROM equities eq
          join follower_accounts fa on fa.acc_no = eq.acc_no
          WHERE eq.acc_no = ?
          ORDER BY ABS(TIMEDIFF(eq.date_time, fa.activated_at)) ASC
          LIMIT 1");
        $stmt->execute([$accNo->value()]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns all deposits to given account between given dates
     *
     * @param AccountNumber $accountNumber
     * @param string $start
     * @param string $end
     * @return float
     */
    public function calculateDeposits(AccountNumber $accountNumber, $start, $end)
    {
        $stmt = $this->dbConn->prepare("
          SELECT SUM(in_out) FROM `equities` 
          WHERE `acc_no` = ? AND `in_out` > 0
          AND `date_time` > ? AND `date_time` < ?");
        $stmt->execute([$accountNumber->value(), $start, $end]);
        return $stmt->fetchColumn();
    }

    /**
     * Returns all withdrawals to given account between given dates
     *
     * @param AccountNumber $accountNumber
     * @param string $start
     * @param string $end
     * @return float
     */
    public function calculateWithdrawals(AccountNumber $accountNumber, $start, $end)
    {
        $stmt = $this->dbConn->prepare("
          SELECT SUM(in_out) FROM `equities` 
          WHERE `acc_no` = ? AND `in_out` < 0
          AND `date_time` > ? AND `date_time` <= ?");
        $stmt->execute([$accountNumber->value(), $start, $end]);
        return $stmt->fetchColumn();
    }

    /**
     * Find equity row closest by dateTime and in_out to given values
     *
     * @param AccountNumber $accountNumber
     * @param string $dateTime
     * @param float $amount
     * @return array
     */
    public function getEquityRowByFee(AccountNumber $accountNumber, $dateTime, $amount)
    {
        $stmt = $this->dbConn->prepare("
          SELECT * FROM `equities` 
          WHERE `acc_no` = ?
          ORDER BY ABS(TIMEDIFF(`date_time`, ?)) ASC, ABS(`in_out` - ?) ASC
          LIMIT 1");
        $stmt->execute([$accountNumber->value(), $dateTime, $amount]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

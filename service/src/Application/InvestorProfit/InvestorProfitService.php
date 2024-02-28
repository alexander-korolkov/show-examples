<?php

namespace Fxtm\CopyTrading\Application\InvestorProfit;

use Doctrine\DBAL\Connection;
use Exception;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Repository\BrokerRepository;
use Memcache;
use PDO;
use Psr\Log\LoggerInterface;

class InvestorProfitService
{
    private const MEMCACHE_BALANCE_EQUITY_PREFIX = 'fc_equity_balance_';

    use LoggerTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var BrokerRepository
     */
    private $brokerRepository;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var Memcache
     */
    private $equityMemcache;

    /**
     * @var int
     */
    private $equityCacheExpirationTime;

    /**
     * InvestorProfitService constructor.
     * @param Connection $connection
     * @param BrokerRepository $brokerRepository
     * @param TradeAccountGateway $tradeAccountGateway
     * @param Memcache $equityMemcache
     * @param $equityCacheExpirationTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        Connection $connection,
        BrokerRepository $brokerRepository,
        TradeAccountGateway $tradeAccountGateway,
        Memcache $equityMemcache,
        $equityCacheExpirationTime,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->brokerRepository = $brokerRepository;
        $this->tradeAccountGateway = $tradeAccountGateway;
        $this->equityMemcache = $equityMemcache;
        $this->equityCacheExpirationTime = $equityCacheExpirationTime;

        $this->setLogger($logger);

    }

    /**
     * @param int $accNo
     * @param string|null $broker
     * @return float[]
     */
    public function getLatestBalanceAndEquity(int $accNo, ?string $broker = null): array
    {
        try {

            if ($broker == null) {
                $broker = $this->brokerRepository->getByFollower($accNo);
            }

            if (null !== $this->equityMemcache && $this->equityCacheExpirationTime > 0) {
                $memcacheKey = static::MEMCACHE_BALANCE_EQUITY_PREFIX . $accNo;
                $memcacheValue = $this->equityMemcache->get($memcacheKey);
                if (false !== $memcacheValue) {
                    $this->logger->info('EquityCacheMonitoring: Equity and balance are taken from cache.');
                    return json_decode($memcacheValue, true);
                }
                $value = $this->getLatestBalanceAndEquityFromWebgate($accNo, $broker);
                $this->equityMemcache->set($memcacheKey, json_encode($value), 0, $this->equityCacheExpirationTime);
                return $value;
            }

            return $this->getLatestBalanceAndEquityFromWebgate($accNo, $broker);
        } catch (Exception $e) {
            $this->logException($e);
        }

        return ['equity'  => 0.0, 'balance' => 0.0];
    }

    /**
     * @param int $accNo
     * @param string $broker
     * @return float[]
     * @throws Exception
     */
    private function getLatestBalanceAndEquityFromWebgate(int $accNo, string $broker): array
    {
        $tradeAcc = $this->tradeAccountGateway->fetchAccountByNumberWithFreshEquity(new AccountNumber($accNo), $broker);
        if (!empty($tradeAcc)) {
            $this->logger->info('EquityCacheMonitoring: Equity and balance are requested from the webgate.');
            return [
                'equity' => floatval($tradeAcc->equity()->amount()),
                'balance' => floatval($tradeAcc->balance()->amount())
            ];
        }

        return ['equity' => 0.0, 'balance' => 0.0];
    }

    /**
     * @param $accountNumber
     * @param $currentEquity
     * @return float
     * @throws \Doctrine\DBAL\DBALException
     */
    public function calculateTodayProfit($accountNumber, $currentEquity) {
        $sql = "
                SELECT
                    TRUNCATE({$currentEquity} - IFNULL(e1.equity, 0) - IFNULL(io.total, 0), 2) AS profit_td
                FROM (SELECT 1 AS id) AS t
                LEFT JOIN (
                    SELECT (equity - in_out) AS equity FROM equities
                    WHERE acc_no = :acc_no
                      AND date_time < CURRENT_DATE() + INTERVAL 1 HOUR
                    ORDER BY date_time DESC LIMIT 1
                ) AS e1 ON 1
                LEFT JOIN (
                    SELECT SUM(in_out) AS total FROM equities
                    WHERE acc_no = :acc_no AND date_time BETWEEN CURRENT_DATE() AND NOW()
                ) AS io ON 1
              ";

        return $this->connection->fetchColumn($sql, ['acc_no' => $accountNumber]);
    }

    /**
     * @param $accountNumber
     * @param $currentEquity
     * @return float
     * @throws \Doctrine\DBAL\DBALException
     */
    public function calculateYesterdayProfit($accountNumber, $currentEquity) {
        $sql = "
                SELECT
                    TRUNCATE(IFNULL(e2.equity, 0) - IFNULL(e1.equity, 0) - IFNULL(io.total, 0), 2) AS profit_yd
                FROM (SELECT 1 AS id) AS t
                LEFT JOIN (
                    SELECT (equity - in_out) AS equity FROM equities
                    WHERE acc_no = :acc_no
                      AND date_time < CURRENT_DATE() + INTERVAL 1 HOUR
                    ORDER BY date_time DESC LIMIT 1
                ) AS e2 ON 1
                LEFT JOIN (
                    SELECT (equity - in_out) AS equity FROM equities
                    WHERE acc_no = :acc_no
                      AND date_time < CURRENT_DATE() - INTERVAL 1 DAY + INTERVAL 1 HOUR
                    ORDER BY date_time DESC LIMIT 1
                ) AS e1 ON 1
                LEFT JOIN (
                    SELECT SUM(in_out) AS total FROM equities
                    WHERE acc_no = :acc_no
                      AND date_time BETWEEN CURRENT_DATE() - INTERVAL 1 DAY AND CURRENT_DATE() - INTERVAL 1 DAY + INTERVAL 1 HOUR 
                ) AS io ON 1
        ";

        return $this->connection->fetchColumn($sql, ['acc_no' => $accountNumber]);
    }

    /**
     * @param $accountNumber
     * @param $currentEquity
     * @return float
     * @throws \Doctrine\DBAL\DBALException
     */
    public function calculateWeekProfit($accountNumber, $currentEquity) {
        $sql = "
                SELECT
                    TRUNCATE({$currentEquity} - e1.equity - io.total, 2) AS profit_1w
                FROM (
                        (
                            SELECT COALESCE(e2.equity, e1.equity) AS equity
                            FROM (SELECT 0 equity) AS e1
                            LEFT JOIN (
                                SELECT acc_no, (equity - in_out) AS equity FROM equities
                                WHERE acc_no = :acc_no
                                    AND date_time < CURRENT_DATE() - INTERVAL 7 DAY + INTERVAL 1 HOUR
                                ORDER BY date_time DESC LIMIT 1
                            ) AS e2 ON 1 = 1
                        ) AS e1,
                        (
                            SELECT SUM(in_out) AS total FROM equities
                            WHERE acc_no = :acc_no
                                AND date_time BETWEEN CURRENT_DATE() - INTERVAL 7 DAY AND NOW()
                        ) AS io
                )
        ";

        return $this->connection->fetchColumn($sql, ['acc_no' => $accountNumber]);
    }

    /**
     * @param $accountNumber
     * @param $currentEquity
     * @return float
     * @throws \Doctrine\DBAL\DBALException
     */
    public function calculateMonthProfit($accountNumber, $currentEquity) {
        $sql = "
                SELECT
                    TRUNCATE({$currentEquity} - e1.equity - io.total, 2) AS profit_1m
                FROM (
                        (
                            SELECT
                                COALESCE(e2.equity, e1.equity) AS equity
                            FROM (SELECT 0 equity) AS e1
                            LEFT JOIN (
                                SELECT acc_no, (equity - in_out) AS equity FROM equities
                                WHERE acc_no = :acc_no
                                    AND date_time < CURRENT_DATE() - INTERVAL 30 DAY + INTERVAL 1 HOUR
                                ORDER BY date_time DESC LIMIT 1
                            ) AS e2 ON 1 = 1
                        ) AS e1,
                        (
                            SELECT SUM(in_out) AS total FROM equities
                            WHERE acc_no = :acc_no
                                AND date_time BETWEEN CURRENT_DATE() - INTERVAL 30 DAY AND NOW()
                        ) AS io
                    )
        ";

        return $this->connection->fetchColumn($sql, ['acc_no' => $accountNumber]);
    }

    /**
     * @param $accountNumber
     * @param $currentEquity
     * @return float
     * @throws \Doctrine\DBAL\DBALException
     */
    public function calculateThreeMonthProfit($accountNumber, $currentEquity) {
        $sql = "
                SELECT
                    TRUNCATE({$currentEquity} - e1.equity - io.total, 2) AS profit_1m
                FROM (
                        (
                            SELECT COALESCE(e2.equity, e1.equity) AS equity
                            FROM (SELECT 0 equity) AS e1
                            LEFT JOIN (
                                SELECT acc_no, (equity - in_out) AS equity FROM equities
                                WHERE acc_no = :acc_no
                                    AND date_time < CURRENT_DATE() - INTERVAL 3 MONTH + INTERVAL 1 HOUR
                                ORDER BY date_time DESC LIMIT 1
                            ) AS e2 ON 1 = 1
                        ) AS e1,
                        (
                            SELECT SUM(in_out) AS total FROM equities
                            WHERE acc_no = :acc_no
                                AND date_time BETWEEN CURRENT_DATE() - INTERVAL 3 MONTH AND NOW()
                        ) AS io
                    )
        ";

        return $this->connection->fetchColumn($sql, ['acc_no' => $accountNumber]);
    }
    /**
     * @param $accountNumber
     * @param $currentEquity
     * @return float
     * @throws \Doctrine\DBAL\DBALException
     */
    public function calculateSixMonthProfit($accountNumber, $currentEquity) {
        $sql = "
                SELECT
                    TRUNCATE({$currentEquity} - e1.equity - io.total, 2) AS profit_1m
                FROM (
                        (
                            SELECT COALESCE(e2.equity, e1.equity) AS equity
                            FROM (SELECT 0 equity) As e1
                            LEFT JOIN (
                                SELECT acc_no, (equity - in_out) AS equity FROM equities
                                WHERE acc_no = :acc_no
                                    AND date_time < CURRENT_DATE() - INTERVAL 6 MONTH + INTERVAL 1 HOUR
                                ORDER BY date_time DESC LIMIT 1
                            ) AS e2 ON 1 = 1
                        ) AS e1,
                        (
                            SELECT SUM(in_out) AS total FROM equities
                            WHERE acc_no = :acc_no
                                AND date_time BETWEEN CURRENT_DATE() - INTERVAL 6 MONTH AND NOW()
                        ) AS io
                )
        ";

        return $this->connection->fetchColumn($sql, ['acc_no' => $accountNumber]);
    }

    /**
     * @param $accountNumber
     * @param $currentEquity
     * @return float
     * @throws \Doctrine\DBAL\DBALException
     */
    public function calculateTotalProfit($accountNumber, $currentEquity) {
        $sql = "
                SELECT TRUNCATE(
                    {$currentEquity} - 
                    COALESCE((SELECT SUM(amount) FROM commission WHERE acc_no = :acc_no), 0) - 
                    COALESCE((SELECT SUM(in_out) FROM equities WHERE acc_no = :acc_no), 0)
                , 2) profit
        ";

        return $this->connection->fetchColumn($sql, ['acc_no' => $accountNumber]);
    }

    /**
     * @param string $accountNumber
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getDailyStats($accountNumber)
    {
        $allTimeData = $this->getStatsForAllTime($accountNumber);
        $lastMonthData = $this->getStatsForLastMonth($accountNumber);

        return [
            'allTime' => $allTimeData,
            'lastMonth' => $lastMonthData,
        ];
    }

    /**
     * @param string $accNo
     * @return array
     * @throws Exception
     */
    private function getStatsForLastMonth(string $accNo): array
    {
        $timestamp = (new DateTime("first day of previous month"))
            ->setTime(0, 0, 0)
            ->format("Y-m-d H:i:s");

        $accNo = intval($accNo);

        $this->connection->executeQuery("SET @acc = {$accNo}");
        // $timestamp points to beginning of day, expected sub query return equity on beginning of month
        $this->connection->executeQuery("
            SET @eq = (
                SELECT IF(fa.opened_at >= '{$timestamp}', 0, e.equity)
                FROM equities AS e 
                LEFT JOIN follower_accounts fa on e.acc_no = fa.acc_no
                WHERE e.acc_no = @acc AND e.date_time >= '{$timestamp}' 
                ORDER BY e.date_time 
                LIMIT 1
            )
        ");
        $stmt = $this->connection->query("
            SELECT 
              t1.acc_no,
              DATE_FORMAT(t1.date_time, '%Y-%m-%d') AS date,
              TRUNCATE(t1.equity - @eq - deposits, 2) AS profit,   
              TRUNCATE(@eq := t1.equity, 2) AS `equity`
            FROM equities AS t1
            INNER JOIN (
               SELECT MAX(d.date_time) as min_date_time FROM equities AS d WHERE d.acc_no = @acc GROUP BY YEAR(d.date_time), MONTH(d.date_time), DAY(d.date_time)
            ) AS md ON md.min_date_time = t1.date_time
            LEFT OUTER JOIN (
               SELECT 
                   SUM(e.in_out) AS deposits,
                   DATE_FORMAT(e.date_time, '%Y-%m-%d') AS `date`
               FROM equities AS e
               WHERE e.acc_no = @acc
               GROUP BY `date`
            ) AS d ON d.date = DATE_FORMAT(t1.date_time, '%Y-%m-%d')
            WHERE t1.acc_no = @acc AND t1.date_time >= '{$timestamp}'
        ");

        $stats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row["date"]] = [
                "accNo" => intval($row["acc_no"]),
                "date" => $row["date"],
                "equity" => floatval($row["equity"]),
                "profit" => floatval($row["profit"]),
            ];
        }

        return $stats;
    }

    /**
     * @param string $accNo
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getStatsForAllTime($accNo)
    {
        $accNo = intval($accNo);

        $this->connection->executeQuery("SET @eq = 0");
        $this->connection->executeQuery("SET @acc = {$accNo}");
        $stmt = $this->connection->query("
                    SELECT
                       t1.acc_no,
                       DATE_FORMAT(t1.date_time, '%Y-%m-%d') AS date,
                       TRUNCATE(t1.equity - @eq - deposits, 2) AS profit,   
                       TRUNCATE(@eq := t1.equity, 2) AS `equity`
                    FROM equities AS t1
                    INNER JOIN (
                        SELECT MAX(d.date_time) as min_date_time FROM equities AS d WHERE d.acc_no = @acc GROUP BY YEAR(d.date_time), MONTH(d.date_time)
                    ) AS md ON md.min_date_time = t1.date_time
                    LEFT OUTER JOIN (
                        SELECT 
                            SUM(e.in_out) AS deposits,
                            DATE_FORMAT(e.date_time, '%Y-%m') AS `date`
                        FROM equities AS e
                        WHERE e.acc_no = @acc
                        GROUP BY `date`
                    ) AS d ON d.date = DATE_FORMAT(t1.date_time, '%Y-%m')
                    WHERE t1.acc_no = @acc
                ");

        $stats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row["date"]] = [
                "accNo" => intval($row["acc_no"]),
                "date" => $row["date"],
                "equity" => floatval($row["equity"]),
                "profit" => floatval($row["profit"]),
            ];
        }

        return $stats;
    }
}

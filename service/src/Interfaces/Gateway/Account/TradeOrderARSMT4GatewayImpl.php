<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\Common\Scheduler\Time;
use Fxtm\CopyTrading\Application\Common\Scheduler\TimeInterval;
use Fxtm\CopyTrading\Application\TradeOrderGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Account\AccountCandle;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\DAO\Account\AccountCandleDao;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;

class TradeOrderARSMT4GatewayImpl implements TradeOrderGateway
{
    /**
     * @var Connection
     */
    private $dbConn;

    /**
     * @var AccountCandleDao
     */
    private $accountCandlesDao;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Connection $dbConn, AccountCandleDao $accountCandlesDao, LoggerInterface $logger)
    {
        $this->dbConn = $dbConn;
        $this->accountCandlesDao = $accountCandlesDao;
        $this->logger = $logger;
    }

    public function getOrdersForPeriod(AccountNumber $accNo, DateTime $start, DateTime $end)
    {
        $stmt = $this->dbConn->prepare("
            SELECT
                o.order,
                CASE o.cmd
                    WHEN 0 THEN 'BUY'
                    WHEN 1 THEN 'SELL'
                    WHEN 2 THEN 'BUY_LIMIT'
                    WHEN 3 THEN 'SELL_LIMIT'
                    WHEN 4 THEN 'BUY_STOP'
                    WHEN 5 THEN 'SELL_STOP'
                    WHEN 6 THEN 'BALANCE'
                    WHEN 7 THEN 'CREDIT'
                    ELSE ''
                END type,
                o.volume / 10000 volume,
                TRIM(TRAILING 'c' FROM o.symbol64) symbol,
                DATE_FORMAT(FROM_UNIXTIME(o.open_ts), '%Y.%m.%d %H:%i:%s') open_time,
                TRUNCATE(o.open_price, 5) open_price,
                DATE_FORMAT(FROM_UNIXTIME(IF(o.cmd = 7, o.open_ts, o.close_ts)), '%Y.%m.%d %H:%i:%s') close_time,
                TRUNCATE(o.close_price, 5) close_price,
                TRUNCATE(o.commission, 4) commission,
                TRUNCATE(o.storage, 4) swap,
                TRUNCATE(o.profit, 4) profit
            FROM orders o force index(login) 
            WHERE o.login = :login
                AND o.close_ts BETWEEN UNIX_TIMESTAMP(:start) AND UNIX_TIMESTAMP(:end)
                AND o.comment NOT LIKE 'Summary trade result%'
        ");
        $stmt->execute([
            "login" => $accNo,
            "start" => $start,
            "end"   => $end
        ]);
        $orders = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $orders[$row["order"]] = $row;
        }
        return $orders;
    }

    public function getOrderHistory(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
            SELECT
                login acc_no,
                cmd type,
                profit equity_diff,
                FROM_UNIXTIME(IF(cmd = 7, open_ts, close_ts)) date_time
            FROM orders
            WHERE login = ? AND ((cmd IN (0, 1, 6) AND close_ts != 0) OR cmd = 7) AND comment NOT LIKE 'Summary trade result%'
            ORDER BY date_time ASC
        ");
        $stmt->execute([$accNo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countOpenPositions(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("SELECT COUNT(o.order) FROM orders o force index(login) WHERE o.login = ? AND o.cmd IN (0, 1) AND o.close_ts = 0;");
        $stmt->execute([$accNo]);
        return $stmt->fetchColumn();
    }

    public function hasOpenPositions(AccountNumber $accNo)
    {
        return 0 < $this->countOpenPositions($accNo);
    }

    public function getApplicableSessions(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
            SELECT
                DISTINCT (TRIM(TRAILING 'c' FROM o.symbol64)) symbol,
                s.day,
                MAKETIME(s.open_hour, s.open_minute, 0) start_t,
                MAKETIME(
                    IF(s.close_hour = 24, 23, s.close_hour),
                    IF(s.close_hour = 24, 59, s.close_minute),
                    IF(s.close_hour = 24, 59, 0)
                ) end_t
            FROM orders o
            JOIN symbol_trade_sessions s ON s.symbol = o.symbol64
            WHERE o.login = ? AND o.cmd IN (0, 1) AND o.close_ts = 0
            ORDER BY s.day
        ");
        $stmt->execute([$accNo]);

        $result = [];
        while (($row = $stmt->fetch())) {
            $result[$row["day"]][$row["symbol"]][] = new TimeInterval(Time::fromString($row["start_t"]), Time::fromString($row["end_t"])->prevSecond());
        }
        return $result;
    }

    public function getApplicableHolidays(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
            SELECT
                DISTINCT (TRIM(TRAILING 'c' FROM o.symbol64)) symbol,
                STR_TO_DATE(CONCAT(h.year, '-', h.month, '-', h.day), '%Y-%c-%e') dt,
                MAKETIME(FLOOR(h.from_minutes / 60), FLOOR(h.from_minutes % 60), 59) start_t,
                MAKETIME(FLOOR(h.to_minutes / 60), FLOOR(h.to_minutes % 60), 59) end_t
            FROM orders o
            JOIN holidays h ON h.symbol = 'All' AND h.enable = 1
            WHERE o.login = :acc_no AND o.cmd IN (0, 1) AND o.close_ts = 0
            HAVING dt >= CURDATE()
            UNION ALL
            SELECT
                DISTINCT (TRIM(TRAILING 'c' FROM o.symbol64)) symbol,
                STR_TO_DATE(CONCAT(h.year, '-', h.month, '-', h.day), '%Y-%c-%e') dt,
                MAKETIME(FLOOR(h.from_minutes / 60), FLOOR(h.from_minutes % 60), 59) start_t,
                MAKETIME(FLOOR(h.to_minutes / 60), FLOOR(h.to_minutes % 60), 59) end_t
            FROM orders o
            JOIN symbols s ON s.symbol = TRIM(TRAILING 'c' FROM o.symbol64)
            JOIN consymbolgroup c ON c.symbol_group_id = s.type
            JOIN holidays h ON h.symbol = c.name AND h.enable = 1
            WHERE o.login = :acc_no AND o.cmd IN (0, 1) AND o.close_ts = 0
            HAVING dt >= CURDATE()
            UNION ALL
            SELECT
                DISTINCT (TRIM(TRAILING 'c' FROM o.symbol64)) symbol,
                STR_TO_DATE(CONCAT(h.year, '-', h.month, '-', h.day), '%Y-%c-%e') dt,
                MAKETIME(FLOOR(h.from_minutes / 60), FLOOR(h.from_minutes % 60), 0) start_t,
                MAKETIME(FLOOR(h.to_minutes / 60), FLOOR(h.to_minutes % 60), 0) end_t
            FROM orders o
            JOIN holidays h ON h.symbol = TRIM(TRAILING 'c' FROM o.symbol64) AND h.enable = 1
            WHERE o.login = :acc_no AND o.cmd IN (0, 1) AND o.close_ts = 0
            HAVING dt >= CURDATE()
            ORDER BY dt
        ");
        $stmt->execute(["acc_no" => $accNo]);

        $result = [];
        while (($row = $stmt->fetch())) {
            $result[$row["dt"]][$row["symbol"]][] = TimeInterval::fromStrings($row["start_t"], $row["end_t"]);
        }
        return $result;
    }

    /**
     * Returns the logins of the accounts without trading since given $date
     * from concrete server limited by $limit
     *
     * @param array $logins
     * @param DateTime $date
     * @return array
     */
    public function getLoginsWithTradingSince(array $logins, DateTime $date) : array
    {
        $logins = self::filterLoginsArray($logins);
        $paramsLength = count($logins);
        if($paramsLength == 0) {
            return [];
        }

        $strFilter = implode(', ', array_fill(0, $paramsLength, '?'));

        /** @var PDOStatement $stmt */
        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT DISTINCT tr.`login` 
                FROM `orders` AS tr 
                WHERE 
                    tr.`login` IN ({$strFilter}) 
                    AND tr.`cmd` IN (0, 1) 
                    AND tr.`close_ts` = 0
            ")
        ;
        $stmt->execute($logins);
        $haveOpenedPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        /** @var PDOStatement $stmt */
        $stmt = $this
            ->dbConn
            ->prepare("
                    SELECT DISTINCT tr.`login` 
                    FROM `orders` AS tr 
                    WHERE tr.`login` IN ({$strFilter})
                        AND tr.`close_ts` >= ? 
                        AND tr.`cmd` IN (0, 1)
            ")
        ;
        $stmt->execute(array_merge($logins, [$date->getTimestamp()]));
        $haveClosedPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_unique(array_merge($haveOpenedPositions, $haveClosedPositions));
    }

    /**
     * Returns array with logins and counts of opened/closed/continued orders in last few days
     *
     * @param array $logins
     * @param string $days
     * @return array
     */
    public function getOrdersCountForLastDays(string $days, array $logins = []): array
    {
        $date = DateTime::NOW()->modify($days)->__toString();
        $loginsConditions = '';
        if(!empty($logins)) {
            $loginsStr = implode(',', $logins);
            $loginsConditions = "o.login IN ({$loginsStr}) AND";
        }
        $stmt = $this->dbConn->prepare("
            SELECT o.login, coalesce(COUNT(o.order), 0) as orders_count
            FROM orders o
            WHERE $loginsConditions o.cmd IN (0, 1) AND (o.close_ts = 0 OR o.close_ts > UNIX_TIMESTAMP(:date) OR o.open_ts > UNIX_TIMESTAMP(:date)) 
            GROUP BY o.login
        ");
        $stmt->bindParam(':date', $date);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if(!empty($logins)) {
            foreach ($logins as $login) {
                if (!isset($result[$login])) {
                    $result[$login] = 0;
                }
            }
        }

        return $result;
    }

    /**
     * Returns array of logins that have orders in the time interval, but didn't trade after this interval
     *
     * @param string $days
     * @return array
     */
    public function getLoginsWithoutTradingInLastDays(string $days): array
    {
        $dateStart = DateTime::NOW()->modify($days)->setTime('00','00')->__toString();
        $dateEnd = DateTime::NOW()->modify($days)->nextDay()->__toString();

        $stmt = $this->dbConn->prepare("SELECT o.login  
            FROM orders o
            WHERE login NOT IN (SELECT o.login
                FROM orders o
                WHERE o.cmd IN (0, 1) AND (o.close_ts = 0 OR o.close_ts > UNIX_TIMESTAMP(:dateEnd) OR o.open_ts > UNIX_TIMESTAMP(:dateEnd)) 
                GROUP BY o.login)
            AND o.cmd IN (0, 1) 
            AND (o.close_ts >= UNIX_TIMESTAMP(:dateStart) OR o.open_ts >= UNIX_TIMESTAMP(:dateStart))
            GROUP BY o.login
        ");
        $stmt->bindParam(':dateStart', $dateStart);
        $stmt->bindParam(':dateEnd', $dateEnd);

        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $result;
    }

    /**
     * Returns array with given logins and counts of closed trade positions
     * for each login
     *
     * @param array $logins
     * @return array
     */
    public function getClosedOrdersCountForAccounts(array $logins): array
    {
        $loginsStr = implode(',', $logins);
        $stmt = $this->dbConn->prepare("
            SELECT o.login, coalesce(COUNT(o.order), 0) as orders_count
            FROM orders o 
            WHERE o.login IN ({$loginsStr}) AND o.cmd IN (0, 1) AND o.close_ts != 0
            GROUP BY o.login
        ");
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($logins as $login) {
            if (!isset($result[$login])) {
                $result[$login] = 0;
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getAccountEquity(AccountNumber $accNo): float
    {
        $accountCandle = $this->accountCandlesDao->get($accNo->value());

        return $accountCandle->getEquityClose();
    }

    /**
     * @inheritDoc
     */
    public function getBulkAccountEquities(array $accountNumbers, DateTime $onDatetime) : array
    {
        $accountCandles = $this->accountCandlesDao->getMany($accountNumbers, $onDatetime);

        $res = [];

        foreach ($accountCandles as $candle) {
            $res[$candle->getLogin()] = $candle->getEquityClose();
        }

        return $res;
    }

    /**
     * Removes non numeric values or values lesser than one from array
     *
     * @param array $array
     * @return array
     */
    private static function filterLoginsArray(array &$array) : array
    {
        return array_filter($array, function ($a) {
            return intval($a) > 0;
        });
    }
}

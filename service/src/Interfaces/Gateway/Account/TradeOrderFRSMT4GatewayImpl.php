<?php


namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;

use Doctrine\DBAL\Driver\Connection;
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
use RuntimeException;

class TradeOrderFRSMT4GatewayImpl implements TradeOrderGateway
{
    private const KEY_OPEN_HOUR = 'open_hour';
    private const KEY_OPEN_MIN = 'open_min';
    private const KEY_CLOSE_HOUR = 'close_hour';
    private const KEY_CLOSE_MIN = 'close_min';

    /** @var Connection|null */
    private $dbConn = null;

    /**
     * @var AccountCandleDao
     */
    private $accountCandlesDao;

    /** @var int */
    private $serverId = -1;

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


    public function setFRSServerId(int $serverId) : self
    {
        $this->serverId = $serverId;

        return $this;
    }

    /**
     * @param AccountNumber $accNo
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     *
     * @deprecated
     *  unused method
     */
    public function getOrdersForPeriod(AccountNumber $accNo, DateTime $start, DateTime $end)
    {
        return [];
    }

    /**
     * @param AccountNumber $accNo
     * @return array
     */
    public function getOrderHistory(AccountNumber $accNo)
    {
        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare("
            SELECT 
                tr.`login` AS acc_no,
                tr.`cmd` AS `type`,
                tr.`profit` AS equity_diff,
                FROM_UNIXTIME(tr.`close_time`) AS `date_time`
            FROM `mt4_trade_record` AS tr
            WHERE tr.`cmd` IN (0, 1, 6, 7)
                AND tr.`login` = ?
                AND tr.`frs_ServerID` = ?
        ");

        $stmt->execute([$accNo, $this->serverId]);

        return [];
    }

    /**
     * @param AccountNumber $accNo
     * @return bool
     */
    public function hasOpenPositions(AccountNumber $accNo)
    {

        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare("
        SELECT EXISTS(
                SELECT * FROM `mt4_trade_record` AS tr WHERE tr.`login` = ? AND tr.`frs_ServerID` = ? AND tr.`close_time` = 0 AND tr.`cmd` IN (0, 1)
            )");
        $stmt->execute([$accNo, $this->serverId]);
        return intval($stmt->fetchColumn()) == 1;
    }

    /**
     * @param AccountNumber $accNo
     * @return int
     */
    public function countOpenPositions(AccountNumber $accNo)
    {

        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare("SELECT COUNT(tr.`order`) FROM `mt4_trade_record` AS tr WHERE tr.`login` = ? AND tr.`frs_ServerID` = ? AND tr.`cmd` IN (0, 1) AND tr.`close_time` = 0");
        $stmt->execute([$accNo, $this->serverId]);
        return intval($stmt->fetchColumn());
    }

    /**
     * @param AccountNumber $accNo
     * @return array
     */
    public function getApplicableSessions(AccountNumber $accNo)
    {
        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare(sprintf("
            SELECT
                DISTINCT tr.`symbol` AS symbol,
                d.`day` AS `day`,
                s.`open_hour` AS `open_hour`,
                s.`open_min` AS `open_min`,
                s.`close_hour` AS `close_hour`,
                s.`close_min` AS `close_min`
            FROM `mt4_trade_record` AS tr
            JOIN (
                SELECT 1 as day
                UNION SELECT 2
                UNION SELECT 3
                UNION SELECT 4
                UNION SELECT 5
                UNION SELECT 6
                UNION SELECT 7
            ) d
            LEFT JOIN `mt4_con_symbol_session` AS s ON s.`symbol` = tr.`symbol` 
                AND s.`frs_ServerID` = tr.`frs_ServerID` AND s.`type` = 2 AND d.`day` = s.`day`   
            WHERE tr.`login` = :%s AND tr.`cmd` IN (0, 1) AND tr.`close_time` = 0 
                AND tr.`frs_ServerID` = :%s AND tr.`frs_RecOperation` IN ('U', 'I', 'X')
        ", self::ACC_NO_PARAM, self::SERVER_ID_PARAM));

        $stmt->execute([self::ACC_NO_PARAM => $accNo, self::SERVER_ID_PARAM => $this->serverId]);

        $result = [];
        while (($row = $stmt->fetch())) {
            $row['symbol'] = rtrim($row['symbol'], 'c');
            if ($this->isNoSession($row)) {
                $result[$row["day"]][$row["symbol"]][] = new TimeInterval(
                    Time::fromInteger(0),
                    Time::fromInteger(0)
                );
            } else {
                $startTime = sprintf('%02d:%02d:00', $row[self::KEY_OPEN_HOUR], $row[self::KEY_OPEN_MIN]);
                $endTime = sprintf(
                    '%02d:%02d:%02d',
                    $row[self::KEY_CLOSE_HOUR] === 24 ? 23 : $row[self::KEY_CLOSE_HOUR],
                    $row[self::KEY_CLOSE_HOUR] === 24 ? 59 : $row[self::KEY_CLOSE_MIN],
                    $row[self::KEY_CLOSE_HOUR] === 24 ? 59 : 0
                );
                $result[$row["day"]][$row["symbol"]][] = new TimeInterval(
                    Time::fromString($startTime),
                    Time::fromString($endTime)->prevSecond()
                );
            }
        }

        return $result;
    }

    /**
     * @param AccountNumber $accNo
     * @return array
     */
    public function getApplicableHolidays(AccountNumber $accNo)
    {
        $stmt = $this->dbConn->prepare("
            SELECT
                DISTINCT (TRIM(TRAILING 'c' FROM tr.`symbol`)) AS symbol,
                STR_TO_DATE(CONCAT(h.`year`, '-', h.`month`, '-', h.`day`), '%Y-%c-%e') AS dt,
                SEC_TO_TIME(h.`from`*60) AS start_t,
                SEC_TO_TIME(h.`to`*60) AS end_t
            FROM `mt4_trade_record` AS tr
            JOIN `mt4_con_holiday` AS h ON h.`symbol` = 'All' AND h.`enable` = 1 AND tr.`frs_ServerID` = h.`frs_ServerID`
            WHERE tr.`cmd` IN (0, 1) AND tr.`close_time` = 0 AND tr.`frs_ServerID` = :server_id AND tr.`login` = :acc_no
            
            HAVING dt >= CURDATE()
            
            UNION ALL
            
            SELECT
                DISTINCT (TRIM(TRAILING 'c' FROM tr.`symbol`)) AS symbol,
                STR_TO_DATE(CONCAT(h.`year`, '-', h.`month`, '-', h.`day`), '%Y-%c-%e') AS dt,
                SEC_TO_TIME(h.`from`*60) AS start_t,
                SEC_TO_TIME(h.`to`*60) AS end_t
            FROM `mt4_trade_record` AS tr
            JOIN `mt4_con_symbol` AS s ON s.`symbol` = TRIM(TRAILING 'c' FROM tr.`symbol`) AND tr.`frs_ServerID` = s.`frs_ServerID`
            JOIN `mt4_con_symbol_group` AS c ON c.`index` = s.`type` AND s.`frs_ServerID` = c.`frs_ServerID`
            JOIN `mt4_con_holiday` AS h ON h.`symbol` = c.`name` AND h.`enable` = 1  AND c.`frs_ServerID` = h.`frs_ServerID`
            WHERE tr.`cmd` IN (0, 1) AND tr.`close_time` = 0 AND tr.`frs_ServerID` = :server_id AND tr.`login` = :acc_no
            HAVING dt >= CURDATE()
            
            UNION ALL
            
            SELECT
                DISTINCT (TRIM(TRAILING 'c' FROM tr.`symbol`)) AS symbol,
                STR_TO_DATE(CONCAT(h.`year`, '-', h.`month`, '-', h.`day`), '%Y-%c-%e') AS dt,
                SEC_TO_TIME(h.`from`*60) AS start_t,
                SEC_TO_TIME(h.`to`*60) AS end_t    
            FROM `mt4_trade_record` AS tr
            JOIN `mt4_con_holiday` AS h ON h.`symbol` = TRIM(TRAILING 'c' FROM tr.`symbol`) AND h.`enable` = 1 AND tr.`frs_ServerID` = h.`frs_ServerID`
            WHERE tr.`cmd` IN (0, 1) AND tr.`close_time` = 0 AND tr.`frs_ServerID` = :server_id AND tr.`login` = :acc_no
            HAVING dt >= CURDATE()
            ORDER BY dt
        ");
        $stmt->execute(["acc_no" => $accNo, 'server_id' => $this->serverId]);

        $result = [];
        while (($row = $stmt->fetch())) {
            $result[$row["dt"]][$row["symbol"]][] = TimeInterval::fromStrings($row["start_t"], $row["end_t"]);
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getLoginsWithTradingSince(array $logins, DateTime $date): array
    {

        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

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
                FROM `mt4_trade_record` AS tr 
                WHERE 
                    tr.`login` IN ({$strFilter}) 
                    AND tr.`frs_ServerID` = ? 
                    AND tr.`cmd` IN (0, 1) 
                    AND tr.`close_time` = 0
            ")
        ;
        $stmt->execute(array_merge($logins, [$this->serverId]));
        $haveOpenedPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        /** @var PDOStatement $stmt */
        $stmt = $this
            ->dbConn
            ->prepare("
                    SELECT DISTINCT tr.`login` 
                    FROM `mt4_trade_record` AS tr 
                    WHERE tr.`login` IN ({$strFilter})
                        AND tr.`close_time` >= ? 
                        AND tr.`frs_ServerID` = ?
                        AND tr.`cmd` IN (0, 1)
            ")
        ;
        $stmt->execute(array_merge($logins, [$date->getTimestamp(),  $this->serverId]));
        $haveClosedPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_unique(array_merge($haveOpenedPositions, $haveClosedPositions));
    }

    /**
     * @inheritDoc
     */
    public function getOrdersCountForLastDays(string $days, array $logins = []): array
    {

        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        if(count($logins) > 0) {

            $logins = self::filterLoginsArray($logins);
            $paramsLength = count($logins);
            if ($paramsLength == 0) {
                return [];
            }
            $strFilter = implode(', ', array_fill(0, $paramsLength, '?'));
            $loginsConditions = "tr.`login` IN ({$strFilter}) AND";
            $params = $logins;
        }
        else {
            $loginsConditions = '';
            $params = [];
        }

        $params[] = DateTime::NOW()->modify($days)->getTimestamp();
        $params[] = DateTime::NOW()->modify($days)->getTimestamp();
        $params[] = $this->serverId;

        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT tr.`login`, COUNT(tr.`order`) as orders_count
                FROM `mt4_trade_record` AS tr
                WHERE 
                    {$loginsConditions} 
                    tr.`cmd` IN (0, 1) 
                    AND (tr.`close_time` >= ? OR tr.`open_time` >= ?)
                    AND tr.frs_ServerID = ?
                GROUP BY tr.login                
            ");

        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        self::fillZeros($result, $logins);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getClosedOrdersCountForAccounts(array $logins): array
    {
        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $logins = self::filterLoginsArray($logins);

        $paramsLength = count($logins);
        if ($paramsLength == 0) {
            return [];
        }
        $strFilter = implode(', ', array_fill(0, $paramsLength, '?'));
        $params = $logins;

        $params[] = $this->serverId;

        $stmt = $this
            ->dbConn
            ->prepare("
                    SELECT tr.`login`, COUNT(tr.`order`) as orders_count
                    FROM `mt4_trade_record` AS tr
                    WHERE 
                        tr.`login` IN ({$strFilter})
                        AND tr.`cmd` IN (0, 1)
                        AND tr.`frs_ServerID` = ?
                    GROUP BY tr.`login`
	            ");

        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        self::fillZeros($result, $logins);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getLoginsWithoutTradingInLastDays(string $days): array
    {

        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        /** @var DateTime $date */
        $date = DateTime::NOW()->modify($days);
        $loginsLeft = $this->getLoginsWithDealsUntilNow($date);

        $date = $date->nextDay();
        $loginsRight = $this->getLoginsWithDealsUntilNow($date);

        return array_filter(
            $loginsLeft,
            function ($login) use ($loginsRight) {
                return !in_array($login, $loginsRight);
            }
        );
    }


    /**
     * @inheritDoc
     */
    public function getAccountEquity(AccountNumber $accNo): float
    {
        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $accountCandle = $this->accountCandlesDao->get($accNo->value());

        return $accountCandle->getEquityClose();
    }

    /**
     * @inheritDoc
     */
    public function getBulkAccountEquities(array $accountNumbers, DateTime $onDatetime) : array
    {
        if($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $accountCandles = $this->accountCandlesDao->getMany($accountNumbers, $onDatetime);

        $res = [];

        foreach ($accountCandles as $candle) {
            $res[$candle->getLogin()] = $candle->getEquityClose();
        }

        return $res;
    }

    private function getLoginsWithDealsUntilNow(DateTime $time) : array
    {
        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT 
                    DISTINCT tr.`login`
                FROM `mt4_trade_record` AS tr
                WHERE 
                    tr.`cmd` IN (0, 1)
                    AND tr.`close_time` >= ? OR tr.`open_time` >= ?
	            ");

        $stmt->execute([$time->getTimestamp()]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Checks each key taken from $keys array in $array, if a key is missed, adds it with $value
     *
     * @param array $array
     * @param array $keys
     * @param int $value
     */
    private static function fillZeros(array &$array, array $keys, int $value = 0) : void
    {
        array_walk($keys, function($key) use(&$array, $value) {
            if(!isset($array[$key])) {
                $array[$key] = $value;
            }
        });
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

    private function isNoSession(array $row): bool
    {
        return is_null($row[self::KEY_OPEN_HOUR]) &&
            is_null($row[self::KEY_OPEN_MIN]) &&
            is_null($row[self::KEY_CLOSE_HOUR]) &&
            is_null($row[self::KEY_CLOSE_MIN]);
    }
}

<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
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

class TradeOrderFRSMT5GatewayImpl implements TradeOrderGateway
{
    private const PATH_COLUMN = 'Path';
    private const SYMBOL_COLUMN = 'Symbol';
    private const SYMBOLS_COLUMN = 'Symbols';

    private const KEY_OPEN_HOURS = 'open_hours';
    private const KEY_OPEN_MINUTES = 'open_minutes';
    private const KEY_CLOSE_HOURS = 'close_hours';
    private const KEY_CLOSE_MINUTES = 'close_minutes';

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


    public function setFRSServerId(int $serverId): self
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
        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare("
            SELECT 
                d.`Login` AS acc_no,
                d.`Action` AS `type`,
                d.`Profit` AS equity_diff,
                FROM_UNIXTIME(d.`Time`) AS `date_time`
            FROM `mt5_deal` AS d
            WHERE d.`Action` IN (0, 1, 2)
                AND d.`Login` = ?
                AND d.`frs_ServerID` = ?
                AND d.`frs_RecOperation` != 'D'
            ORDER BY `date_time` ASC
        ");

        $stmt->execute([$accNo, $this->serverId]);

        return $stmt->fetchAllAssociative();
    }

    /**
     * @param AccountNumber $accNo
     * @return bool
     */
    public function hasOpenPositions(AccountNumber $accNo)
    {

        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare("SELECT EXISTS(SELECT * FROM `mt5_position` AS p WHERE p.`Login` = ? AND p.`frs_ServerID` = ? AND p.`frs_RecOperation` != 'D')");
        $stmt->execute([$accNo, $this->serverId]);
        $this->logger->info("hasOpenPositions: Executing query: SELECT EXISTS(SELECT * FROM `mt5_position` AS p WHERE p.`Login` = {$accNo} AND p.`frs_ServerID` = {$this->serverId} AND p.`frs_RecOperation` != 'D')");
        return intval($stmt->fetchColumn()) == 1;
    }

    /**
     * @param AccountNumber $accNo
     * @return int
     */
    public function countOpenPositions(AccountNumber $accNo)
    {

        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare("SELECT COUNT(p.`Position`) FROM `mt5_position` AS p WHERE p.`Login` = ? AND p.`frs_ServerID` = ? AND p.`frs_RecOperation` != 'D'");
        $stmt->execute([$accNo, $this->serverId]);
        return intval($stmt->fetchColumn());
    }

    /**
     * @param AccountNumber $accNo
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    public function getApplicableSessions(AccountNumber $accNo)
    {

        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $stmt = $this->dbConn->prepare(sprintf("
            SELECT
                DISTINCT p.`symbol` AS symbol,
                d.`Day` AS `day`,
                s.`OpenHours` AS `open_hours`,
                s.`OpenMinutes` AS `open_minutes`,
                s.`CloseHours` AS `close_hours`,
                s.`CloseMinutes` AS `close_minutes`
            FROM `mt5_position` AS p
            JOIN (
                SELECT 1 as Day
                UNION SELECT 2
                UNION SELECT 3
                UNION SELECT 4
                UNION SELECT 5
                UNION SELECT 6
                UNION SELECT 7
            ) d
            LEFT JOIN `mt5_con_symbol_session` AS s ON s.`Symbol` = p.`Symbol` AND s.`frs_ServerID` = :%s 
                AND s.`Type` = 2 AND d.Day = s.Day
            WHERE p.`Login` = :%s AND p.`frs_ServerID` = :%s AND p.`frs_RecOperation` != 'D'
        ", self::SERVER_ID_PARAM, self::ACC_NO_PARAM, self::SERVER_ID_PARAM));

        $stmt->execute([self::ACC_NO_PARAM => $accNo, self::SERVER_ID_PARAM => $this->serverId]);

        $result = [];
        while (($row = $stmt->fetch())) {
            if ($this->isNoSession($row)) {
                $result[$row["day"]][$row["symbol"]][] = new TimeInterval(
                    Time::fromInteger(0),
                    Time::fromInteger(0)
                );
            } else {
                $startTime = sprintf('%02d:%02d:00', $row[self::KEY_OPEN_HOURS], $row[self::KEY_OPEN_MINUTES]);
                $endTime = sprintf(
                    '%02d:%02d:%02d',
                    $row[self::KEY_CLOSE_HOURS] === 24 ? 23 : $row[self::KEY_CLOSE_HOURS],
                    $row[self::KEY_CLOSE_HOURS] === 24 ? 59 : $row[self::KEY_CLOSE_MINUTES],
                    $row[self::KEY_CLOSE_HOURS] === 24 ? 59 : 0
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
    public function getApplicableHolidays(AccountNumber $accNo): array
    {
        $holidays = $this->getHolidaysMt5();
        $symbols = $this->getSymbols($accNo);

        $result = [];
        foreach ($symbols as $symbol) {
            foreach ($holidays as $holiday) {
                $holidayDate = DateTime::createFromFormat(
                    'Y-n-j',
                    sprintf('%d-%d-%d', $holiday['year'], $holiday['month'], $holiday['day'])
                );
                $currentDate = DateTime::createFromFormat('Y-n-j', date('Y-n-j'));
                if ($holidayDate < $currentDate) {
                    continue;
                }

                if ($this->isHolidayApplied($symbol, $holiday[self::SYMBOLS_COLUMN])) {
                    $result[$holidayDate->format('Y-m-d')][$symbol[self::SYMBOL_COLUMN]][] =
                        TimeInterval::fromStrings(
                            gmdate('H:i:s', $holiday['workFrom'] * 60),
                            gmdate('H:i:s', $holiday['workTo'] * 60)
                        );
                }
            }
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getLoginsWithTradingSince(array $logins, DateTime $date): array
    {

        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $logins = self::filterLoginsArray($logins);
        $paramsLength = count($logins);
        if ($paramsLength == 0) {
            return [];
        }

        $strFilter = implode(', ', array_fill(0, $paramsLength, '?'));

        /** @var PDOStatement $stmt */
        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT DISTINCT p.`Login` 
                FROM `mt5_position` AS p 
                WHERE p.`Login` IN ({$strFilter}) 
                    AND p.`frs_ServerID` = ? 
                    AND p.`frs_RecOperation` != 'D'
            ")
        ;

        $stmt->execute(array_merge($logins, [$this->serverId]));
        $haveOpenedPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        /** @var PDOStatement $stmt */
        $stmt = $this
            ->dbConn
            ->prepare("
                    SELECT DISTINCT d.`Login` 
                    FROM `mt5_deal` AS d 
                    WHERE d.`Login` IN ({$strFilter})
                        AND d.`Time` >= ? 
                        AND d.frs_ServerID = ?
                        AND d.`frs_RecOperation` != 'D'
            ")
        ;

        $stmt->execute(array_merge($logins, [$date->getTimestamp(),  $this->serverId]));
        $haveDeals = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_unique(array_merge($haveOpenedPositions, $haveDeals));
    }

    /**
     * @inheritDoc
     */
    public function getOrdersCountForLastDays(string $days, array $logins = []): array
    {

        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        if (count($logins) > 0) {
            $logins = self::filterLoginsArray($logins);
            ;
            $paramsLength = count($logins);
            if ($paramsLength == 0) {
                return [];
            }
            $strFilter = implode(', ', array_fill(0, $paramsLength, '?'));
            $loginsConditions = "d.`Login` IN ({$strFilter}) AND";
            $params = $logins;
        } else {
            $loginsConditions = '';
            $params = [];
        }

        $params[] = DateTime::NOW()->modify($days)->getTimestamp();
        $params[] = $this->serverId;

        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT d.`Login`, coalesce(COUNT(d.`Deal`), 0) as orders_count
                FROM `mt5_deal` AS d
                WHERE 
                    {$loginsConditions} 
                    d.`Action` IN (0, 1) 
                    AND d.`Time` >= ?
                    AND d.`frs_ServerID` = ?
                    AND d.`frs_RecOperation` != 'D'
                GROUP BY d.`Deal`                
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
        if ($this->serverId < 0) {
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
                    SELECT 
                        d.`Login`,
                        COUNT(d.`Deal`) AS orders_count
                    FROM `mt5_deal` AS d
                    WHERE 
                        d.`Login` IN ({$strFilter})
                        AND d.`Entry` IN (1, 2)
                        AND d.`frs_ServerID` = ?
                        AND d.`frs_RecOperation` != 'D'
                    GROUP BY d.`Login`
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
        if ($this->serverId < 0) {
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
        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $accountCandle = $this->accountCandlesDao->get($accNo->value());

        return $accountCandle->getEquityClose();
    }

    /**
     * @inheritDoc
     */
    public function getBulkAccountEquities(array $accountNumbers, DateTime $onDatetime): array
    {
        if ($this->serverId < 0) {
            throw new RuntimeException("Illegal state: Invalid server ID");
        }

        $accountCandles = $this->accountCandlesDao->getMany($accountNumbers, $onDatetime);

        $res = [];

        foreach ($accountCandles as $candle) {
            $res[$candle->getLogin()] = $candle->getEquityClose();
        }

        return $res;
    }

    private function getLoginsWithDealsUntilNow(DateTime $time): array
    {
        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT 
                    DISTINCT d.`Login`
                FROM `mt5_deal` AS d
                WHERE 
                    d.`Action` IN (0, 1)
                    AND d.`Time` >= ?  
                    AND d.`frs_RecOperation` != 'D'
	            ");

        $stmt->execute([$time->getTimestamp()]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Returns list of parameters of currently open position for specified account
     * @param AccountNumber $accNo
     * @return array
     */
    public function getCurrentlyOpenPositions(AccountNumber $accNo): array
    {
        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT 
                       p.`Symbol` as `item`,
                       p.`Action` as `type`,
                       p.`Volume` / 10000.0 as `volume`
                FROM `mt5_position` AS p
                WHERE 
                    p.`Action` IN (0, 1) AND p.`Login` = ? AND p.`frs_ServerID` = ? AND p.`frs_RecOperation` != 'D'
	            ");

        $stmt->execute([$accNo->value(), $this->serverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns list with specified length of parameters of recently closed or partially closed
     * positions for specified account. It combines entry and exiting deals
     *
     * @param AccountNumber $accNo
     * @param int $limit
     * @return array
     */
    public function getRecentlyClosedPositions(AccountNumber $accNo, int $limit = 50): array
    {
        $stmt = $this
            ->dbConn
            ->prepare("
                SELECT 
                    ins.`PositionID` AS `position`,
                    ins.`Action` AS `type`,
                    ROUND(ins.`Volume` / 10000, 4) AS `volume`,	
                    ROUND(outs.`VolumeClosed` / 10000, 4) AS `volume_closed`,
                    ins.`Symbol` AS `item`,
                    ins.`Time` AS `open_time`,
                    ins.`Price` AS `open_price`,
                    outs.`Time` AS `close_time`,
                    outs.`Price` AS `close_price`,
                    ROUND((ins.`Commission` * (outs.`VolumeClosed` / ins.`Volume`) + outs.`Commission`), 4) AS `commission`,
                    ROUND(outs.Storage, 4) AS `swap`,
                    ROUND(outs.Profit, 4) AS `profit`                
                FROM `mt5_deals` AS ins
                    LEFT OUTER JOIN `mt5_deals` AS outs ON outs.`frs_RecOperation` != 'D' AND ins.`frs_ServerID` = outs.`frs_ServerID` AND ins.`PositionID` = outs.`PositionID` AND outs.`Entry` = 1 
                WHERE 
                    ins.`Login` = ? AND
                    ins.`frs_ServerID` = ? AND 
                    ins.`frs_RecOperation` != 'X' AND
                    ins.`Action` IN (0, 1) AND
                    ins.`Entry` = 0 AND
                    outs.`Deal` IS NOT NULL
                ORDER BY ins.`Time` ASC
                LIMIT {$limit}
            ");
        $stmt->execute([$accNo->value(), $this->serverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Checks each key taken from $keys array in $array, if a key is missed, adds it with $value
     *
     * @param array $array
     * @param array $keys
     * @param int $value
     */
    private static function fillZeros(array &$array, array $keys, int $value = 0): void
    {
        array_walk($keys, function ($key) use (&$array, $value) {
            if (!isset($array[$key])) {
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
    private static function filterLoginsArray(array &$array): array
    {
        return array_filter($array, function ($a) {
            return intval($a) > 0;
        });
    }

    private function getSymbols(AccountNumber $accNo): array
    {
        $stmt = $this->dbConn->prepare(sprintf("
            SELECT 
                DISTINCT p.`Symbol` as %s,
                s.`Path` AS %s
            FROM `mt5_position` AS p
            JOIN `mt5_con_symbol` AS s ON s.`frs_ServerID` = p.`frs_ServerID` AND s.`Symbol` = p.`Symbol`  
            WHERE p.`frs_ServerID` = :%s
                AND p.`frs_RecOperation` != 'D' AND p.`Login` = :%s
         ", self::SYMBOL_COLUMN, self::PATH_COLUMN, self::SERVER_ID_PARAM, self::ACC_NO_PARAM));
        $stmt->execute([self::ACC_NO_PARAM => $accNo, self::SERVER_ID_PARAM => $this->serverId]);
        return $stmt->fetchAll(FetchMode::ASSOCIATIVE);
    }

    private function getHolidaysMt5(): array
    {
        $holidaysStmt = $this->dbConn->prepare(sprintf("
            SELECT h.`Year` AS year,
                   h.`Month` AS month,
                   h.`Day` AS day,
                   h.`WorkFrom` AS workFrom,
                   h.`WorkTo` AS workTo,
                   h.`Symbols` AS %s
            FROM `mt5_con_holiday` as h
            WHERE h.`frs_ServerID` = :%s AND h.`Mode` = 1 AND h.`frs_RecOperation` != 'D' 
        ", self::SYMBOLS_COLUMN, self::SERVER_ID_PARAM));
        $holidaysStmt->execute([self::SERVER_ID_PARAM => $this->serverId]);
        return $holidaysStmt->fetchAll(FetchMode::ASSOCIATIVE);
    }

    private function isHolidayApplied(array $symbol, string $holidaySymbolColumn): bool
    {
        if (
            $holidaySymbolColumn === '*' ||
            $holidaySymbolColumn === $symbol[self::SYMBOL_COLUMN]
        ) {
            return true;
        }

        $holidaySymbols = array_map(function (string $elem) {
            return trim($elem);
        }, explode(',', $holidaySymbolColumn));

        $isApplicable = true;
        foreach ($holidaySymbols as $holidaySymbol) {
            if (
                substr($holidaySymbol, 0, 1) === '!' &&
                $this->isSymbolInHolidayRegex($symbol[self::PATH_COLUMN], substr($holidaySymbol, 1))
            ) {
                $isApplicable = false;
            }
        }
        if ($isApplicable) {
            foreach ($holidaySymbols as $holidaySymbol) {
                if (
                    substr($holidaySymbol, 0, 1) !== '!' &&
                    $this->isSymbolInHolidayRegex($symbol[self::PATH_COLUMN], $holidaySymbol)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isSymbolInHolidayRegex(
        string $symbol,
        string $holidaySymbol
    ): bool {
        $regex = '/' . str_replace(
            '*',
            '[a-zA-Z0-9_\\\\]*',
            str_replace('\\', '\\\\', $holidaySymbol)
        ) . '/';
        return preg_match_all($regex, $symbol);
    }

    private function isNoSession(array $row): bool
    {
        return is_null($row[self::KEY_OPEN_HOURS]) &&
            is_null($row[self::KEY_OPEN_MINUTES]) &&
            is_null($row[self::KEY_CLOSE_HOURS]) &&
            is_null($row[self::KEY_CLOSE_MINUTES]);
    }
}

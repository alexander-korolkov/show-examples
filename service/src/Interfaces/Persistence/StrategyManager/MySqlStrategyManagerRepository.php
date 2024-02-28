<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\StrategyManager;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\StrategyManager\StrategyManagerRepository;

class MySqlStrategyManagerRepository implements StrategyManagerRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * MySqlStrategyManagerRepository constructor.
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    /**
     * @param string $accountNumber
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function get($accountNumber)
    {
        $sql = '
            SELECT 
              les.acc_no as accountNumber,
              les.acc_name as accountName,
              les.manager_name as managerName,
              les.avatar as avatar,
              les.country as country,
              les.acc_curr as currency,
              les.is_veteran as isVeteran,
              les.chart as chart,
              les.chart as chart,
              les.age_in_days as age,
              les.remun_fee as profitShare,
              les.leverage as leverage,
              les.swap_free as isSwapFree,
              les.rank as rank,
              les.pop_points as popularity,
              les.pop as popularity,
              les.followers as investorsCount,
              les.total_funds as investorsFunds,
              les.risk_level as riskLevel,
              les.max_drawdown as maxDrawdown,
              les.min_deposit as minDeposit,
              les.min_deposit_in_safety_mode as minDepositInSafetyMode,
              COALESCE(les.profit, \'0.00\') as profit,
              COALESCE(les.profit_1d, \'0.00\') as profitTd,
              COALESCE(les.profit_1w, \'0.00\') as profit1w,
              COALESCE(les.profit_1m, \'0.00\') as profit1m,
              COALESCE(les.profit_3m, \'0.00\') as profit3m,
              COALESCE(les.profit_6m, \'0.00\') as profit6m
            FROM leader_equity_stats les
            WHERE les.acc_no = ?
        ';

        $leaderEquityStats = $this->connection->fetchAssoc($sql, [$accountNumber]);

        $profitKeys = [
            'profit',
            'profitTd',
            'profit1w',
            'profit1m',
            'profit3m',
            'profit6m',
        ];

        foreach ($profitKeys as $profitKey) {
            if (null === $leaderEquityStats[$profitKey]) {
                $leaderEquityStats[$profitKey] = '0.00';
            }
        }

        return $leaderEquityStats;
    }

    /**
     * @param string $accountNumber
     * @param string $date
     * @return array
     */
    public function getTradeInstrumentsStats($accountNumber, $date = null)
    {
        $period = 'all';
        $params = [
            'acc_no' => $accountNumber,
        ];
        $symbolDateCondition = $durationDateCondition = '';

        if ($date) {
            $period = 'month';
            $params['start'] = $date;
            $params['finish'] = date("Y-m-t", strtotime($date));
            $symbolDateCondition = ' AND date = :start';
            $durationDateCondition = ' AND date BETWEEN :start AND :finish';
        }

        $sql = "
            SELECT
                srs.symbol64 AS symbol,
                srs.buy,
                srs.sell,
                srs.profit,
                srs.loss,
                srs.stop_loss AS stopLoss,
                srs.take_profit AS takeProfit,
                srs.avg_profit AS avgProfit,
                srs.avg_loss AS avgLoss,
                srs.avg_pips AS avgPips,
                srs.avg_tp AS avgTp,
                srs.avg_sl AS avgSl,
                sd.avg_duration AS avgDuration
            FROM site_review_symbol_{$period} srs
            
            LEFT JOIN (
              SELECT 
                symbol64,
                ROUND(SUM(srsd.duration) / SUM(srsd.buy + srsd.sell)) AS avg_duration
              FROM site_review_symbol_day srsd
              WHERE srsd.acc_id = :acc_no {$durationDateCondition}
              GROUP BY symbol64
            ) AS sd ON sd.symbol64 = srs.symbol64
            
            WHERE
              srs.acc_id = :acc_no
              {$symbolDateCondition}
        ";

        $instruments = $this->connection->fetchAll($sql, $params);
        if (!empty($instruments)) {
            usort(
                $instruments,
                function ($a, $b) {
                    return ($b['sell'] + $b['buy']) - ($a['sell'] + $a['buy']);
                }
            );
        }

        return $instruments;
    }

    /**
     * @param string $accountNumber
     * @param string $date
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getTradingReview($accountNumber, $date = null)
    {
        $period = 'all';
        $params = [
            'acc_no' => $accountNumber,
        ];
        $totalDateCondition = $durationTotalDateCondition = $statsDateCondition = '';
        $statsSelect = "
            ext.acc_id,
            ext.biggest_profit,
            ext.biggest_loss,
            ext.max_open_orders,
            ext.max_cons_profit,
            ext.max_cons_loss
        ";

        if ($date) {
            $period = 'month';
            $params['start'] = $date;
            $params['finish'] = date("Y-m-t", strtotime($date));
            $totalDateCondition = ' AND date = :start';
            $durationTotalDateCondition = ' AND date BETWEEN :start AND :finish';
            $statsDateCondition = ' AND date BETWEEN :start AND :finish';
            $statsSelect = "              
                ext.acc_id,
                MAX(ext.biggest_profit) AS biggest_profit,
                MAX(ext.biggest_loss) AS biggest_loss,
                MAX(ext.max_open_orders) AS max_open_orders,
                MAX(ext.max_cons_profit) AS max_cons_profit,
                MAX(ext.max_cons_loss) AS max_cons_loss
            ";
        }

        $sql = "
            SELECT
              SUM(t.stop_loss) AS total_stop_loss,
              SUM(t.buy) AS total_buy,
              SUM(t.sell) AS total_sell,
              SUM(t.profit) AS total_profit,
              SUM(t.take_profit) AS total_take_profit,
              SUM(t.loss) AS total_loss,
              SUM(t.buy + sell) AS total_trades
              
            FROM site_review_symbol_{$period} t
            
            LEFT JOIN (
              SELECT
                acc_id,
                ROUND(SUM(srsd.duration) / SUM(srsd.buy + srsd.sell)) AS avg_duration
              FROM site_review_symbol_day srsd
              WHERE srsd.acc_id = :acc_no {$durationTotalDateCondition}
            ) AS std ON std.acc_id = t.acc_id
            
            LEFT JOIN (
              SELECT 
                {$statsSelect}
              FROM site_review_ext_{$period} ext
              WHERE
                ext.acc_id = :acc_no
                {$statsDateCondition}
            ) AS stats ON stats.acc_id = t.acc_id
            
            WHERE
              t.acc_id = :acc_no
              {$totalDateCondition}
        ";

        return $this->connection->fetchAssoc($sql, $params);
    }

    /**
     * @param string $accountNumber
     * @param string $type
     * @param string $date
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getTradingsPeriodReviewByType($accountNumber, $type, $date = null)
    {
        $duration = 'all';
        $params = ['acc_id' => $accountNumber];
        $dateCondition = '';

        if ($date) {
            $duration = 'month';
            $params['date'] = $date;
            $dateCondition =' AND date = :date';
        }

        $sql = "
            SELECT * 
            FROM site_review_{$type}_{$duration} 
            WHERE acc_id = :acc_id {$dateCondition} 
            ORDER BY acc_id ASC
        ";

        $result = $this->connection->fetchAssoc($sql, $params);
        if ($result === false) {
            $result = [];
        }

        if (isset($result['acc_id'])) {
            $result['accId'] = $result['acc_id'];
        }

        return $result;
    }

    /**
     * @param  bool $top  show only top managers (optional)
     * @param  bool $euOnly  show only managers for eu (optional)
     * @param  int $minInvestorsCount  show only managers with investors\&quot; count bigger or equal than value (optional)
     * @param  int $minProfit  show only managers with profit bigger or equal than value (optional)
     * @param  int $maxProfitShare  show only managers with fee lower or equal than value (optional)
     * @param  int $maxDrawdown  show only managers with drawdown lower or equal than value (optional)
     * @param  int $maxRiskLevel  show only managers with risk level lower or equal than value (optional)
     * @param  int $minAge  show only managers with age in months bigger or equal than value (optional)
     * @param  bool $veteran  show only veteran managers (optional)
     * @param  string $country  show only managers with country which contains the value (optional)
     * @param  string $name  show only managers with account name which contains the value (optional)
     * @param  string $sortBy  show filtered managers sorted by given name of field (optional)
     * @param  string $sortOrder  ascendant or descendant sorting order (optional)
     * @param  float $limit   (optional)
     * @param  float $offset   (optional)
     *
     * @return array
     *
     */
    public function getAll($top = null, $euOnly = null, $minInvestorsCount = null, $minProfit = null, $maxProfitShare = null, $maxDrawdown = null, $maxRiskLevel = null, $minAge = null, $veteran = null, $country = null, $name = null, $sortBy = null, $sortOrder = null, $limit = null, $offset = null)
    {
        $sql = '
            SELECT 
              les.acc_no as accountNumber,
              les.acc_name as accountName,
              les.manager_name as managerName,
              les.avatar as avatar,
              les.country as country,
              les.acc_curr as currency,
              les.is_veteran as isVeteran,
              les.chart as chart,
              les.age_in_days as age,
              les.remun_fee as profitShare,
              les.leverage as leverage,
              les.swap_free as isSwapFree,
              les.rank as rank,
              les.pop_points as popularity,
              les.pop as popularity,
              les.followers as investorsCount,
              les.total_funds as investorsFunds,
              les.risk_level as riskLevel,
              les.max_drawdown as maxDrawdown,
              les.min_deposit as minDeposit,
              les.min_deposit_in_safety_mode as minDepositInSafetyMode,
              COALESCE(les.profit, \'0.00\') as profit,
              COALESCE(les.profit_1d, \'0.00\') as profitTd,
              COALESCE(les.profit_1w, \'0.00\') as profit1w,
              COALESCE(les.profit_1m, \'0.00\') as profit1m,
              COALESCE(les.profit_3m, \'0.00\') as profit3m,
              COALESCE(les.profit_6m, \'0.00\') as profit6m
            FROM leader_equity_stats les
            WHERE is_public = 1
        ';

        if ($top !== null) {
            $sql .= ' AND les.rank > 0';
        }

        if ($euOnly !== null) {
            $sql .= " AND les.flags = 'eu'";
        }

        if ($minInvestorsCount !== null) {
            $sql .= " AND les.followers >= $minInvestorsCount";
        }

        if ($minProfit !== null) {
            $sql .= " AND les.profit >= $minProfit";
        }

        if ($maxProfitShare !== null) {
            $sql .= " AND les.remun_fee <= $maxProfitShare";
        }

        if ($maxDrawdown !== null) {
            $sql .= " AND les.max_drawdown <= $maxDrawdown";
        }

        if ($maxRiskLevel !== null) {
            $sql .= " AND les.risk_level <= $maxRiskLevel";
        }

        if ($minAge !== null) {
            $dt = DateTime::NOW()->relativeDatetime("- $minAge months");
            $sql .= " AND les.activated_at >= $dt";
        }

        if ($veteran !== null) {
            $sql .= " AND les.is_veteran = 1";
        }

        if ($country !== null) {
            $sql .= " AND les.country = $country";
        }

        if ($name !== null) {
            $sql .= " AND les.acc_name COLLATE UTF8_GENERAL_CI LIKE '%$name%'";
        }

        if ($sortBy !== null) {
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'asc';
            }
            $profitSortOrder = $sortOrder == 'asc' ? 'desc' : 'asc';
            $sql .= " ORDER BY $sortBy $sortOrder, les.profit $profitSortOrder";
        }

        if ($limit !== null) {
            $sql .= " LIMIT $limit";
        }

        if ($offset !== null) {
            $sql .= " OFFSET $offset";
        }

        return $this->connection->fetchAll($sql);
    }
}

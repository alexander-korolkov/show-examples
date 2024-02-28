<?php


namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\FetchMode;
use Fxtm\CopyTrading\Application\FollowerTradeHistory;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class FollowerTradeHistoryFrsMigrated implements FollowerTradeHistory
{

    /**
     * @var Connection
     */
    private $frsConnectionFrom;

    /**
     * @var int
     */
    private $frsIDFrom;

    /**
     * @var Connection
     */
    private $frsConnectionTo;

    /**
     * @var int
     */
    private $frsIDTo;

    /**
     * @var int
     */
    private $migrationTimeStamp;

    public function setFrsConnectionFrom(Connection $connection): FollowerTradeHistoryFrsMigrated
    {
        $this->frsConnectionFrom = $connection;
        return $this;
    }

    public function setFrsIdFrom(int $id): FollowerTradeHistoryFrsMigrated
    {
        $this->frsIDFrom = $id;
        return $this;
    }

    public function setFrsConnectionTo(Connection $connection): FollowerTradeHistoryFrsMigrated
    {
        $this->frsConnectionTo = $connection;
        return $this;
    }

    public function setFrsIdTo(int $id): FollowerTradeHistoryFrsMigrated
    {
        $this->frsIDTo = $id;
        return $this;
    }

    public function setMigrationDate(DateTime $dateTime): FollowerTradeHistoryFrsMigrated
    {
        $this->migrationTimeStamp = $dateTime->getTimestamp();
        return $this;
    }


    /**
     * @inheritDoc
     * @param AccountNumber $number
     * @param DateTime $from
     * @param DateTime $to
     * @return int
     */
    public function getClosedOrdersCount(AccountNumber $number, DateTime $from, DateTime $to): int
    {
        $tsFrom = $from->getTimestamp();
        $tsTo = $to->getTimestamp();

        $count = 0;
        if($tsFrom < $this->migrationTimeStamp) {
            // fetch from frs
            $statement = $this
                ->frsConnectionFrom
                ->prepare("
                    SELECT count(*) 
                    FROM `mt4_trade_record` AS tr
                    WHERE 
                          tr.frs_RecOperation <> 'D' AND
                          tr.frs_ServerID = ? AND
                          tr.login = ? AND 
                          tr.cmd IN (0, 1) AND
                          tr.close_time BETWEEN ? AND ?
                ");
            $statement->execute([$this->frsIDFrom, $number->value(), $tsFrom, min($tsTo, $this->migrationTimeStamp)]);
            $count += intval($statement->fetchColumn(0));
        }

        if($tsTo > $this->migrationTimeStamp) {
            // from MT5 frs
            $statement = $this
                ->frsConnectionTo
                ->prepare("
                SELECT count(*) FROM (
                    SELECT 
                        ins.PositionID AS `position`
                    FROM `mt5_deal` AS ins
                        LEFT OUTER JOIN `mt5_deal` AS outs ON ins.PositionID = outs.PositionID AND outs.Entry = 1 
                    WHERE 
                        ins.frs_ServerID = ? AND outs.frs_ServerID = ? AND
                        ins.frs_RecOperation <> 'D' AND outs.frs_RecOperation <> 'D' AND
                        ins.Login = ? AND
                        ins.Time >= ? AND ins.Time < ? AND 
                        ins.Action IN (0, 1) AND 
                        ins.Entry = 0 AND
                        outs.Deal IS NOT NULL                    
                ) as mrs
                ");
            $statement->execute(
                [$this->frsIDTo, $this->frsIDTo, $number->value(), max($tsFrom, $this->migrationTimeStamp), $tsTo]
            );
            $count += intval($statement->fetchColumn(0));
        }
        return $count;
    }

    /**
     * @inheritdoc
     * @param AccountNumber $number
     * @param int $limit
     * @return array
     */
    public function getLatestClosedOrders(AccountNumber $number, int $limit): array
    {
        $result = [];

        // Firstly fetch from mt5
        $statement = $this
            ->frsConnectionTo
            ->prepare("
                SELECT 
                       
                    'frs' as `src`,
                
                    ins.PositionID AS `ticket`,
                    IF(ins.Action = 0, 'BUY', 'SELL') AS `orderType`,
                    ROUND(outs.VolumeClosed / 10000, 4) AS `volume`,
                    ROUND(ins.Volume / 10000, 4) AS `size1`,	
                    ins.Symbol AS `symbol`,
                    
                    ins.Time AS `openTime`,
                    ins.Price AS `openPrice`,
                    
                    outs.Time AS `closeTime`,
                    outs.Price AS `closePrice`,
                    
                    ROUND((ins.Commission * (outs.VolumeClosed / ins.Volume) + outs.Commission), 4) AS `commission`,
                    ROUND(outs.Storage, 4) AS `swap`,
                    ROUND(outs.Profit, 4) AS `profit`
                
                FROM `mt5_deal` AS ins
                    LEFT OUTER JOIN `mt5_deal` AS outs ON ins.PositionID = outs.PositionID AND ins.frs_ServerID = outs.frs_ServerID AND outs.Entry = 1 AND outs.frs_RecOperation <> 'D'
                WHERE
                    ins.frs_ServerID = ? AND
                    ins.frs_RecOperation <> 'D' AND
                    ins.Login = ? AND
                    ins.Action IN (0, 1) AND 
                    ins.Entry = 0 AND
                    outs.Deal IS NOT NULL
                
                ORDER BY outs.Time DESC
                LIMIT {$limit} 
            ");
        $statement->execute([$this->frsIDTo, $number->value()]);
        $result = array_merge($result, $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE)));

        // Secondly if limit is not reached try fetch more data from mt4
        if(count($result) < $limit) {
            $limit -= count($result);
            $statement = $this
                ->frsConnectionFrom
                ->prepare("
                      SELECT
                         'frs' as `src`,
                         tr.order AS ticket, 
                         IF (tr.cmd = 0, 'BUY', 'SELL') AS `orderType`,
                         tr.volume AS `volume`,
                         tr.symbol AS `symbol`,
                         tr.open_time AS `openTime`, 
                         tr.open_price AS `openPrice`, 
                         tr.close_time AS `closeTime`, 
                         tr.close_price AS `closePrice`, 
                         tr.sl AS `stopLoss`, 
                         tr.tp AS `takeProfit`, 
                         tr.commission AS `commission`, 
                         tr.storage AS `swap`, 
                         tr.profit AS `profit`
                      FROM `mt4_trade_record` AS tr
                      WHERE
                            tr.frs_RecOperation <> 'D' AND
                            tr.frs_ServerID = ? AND                            
                            tr.login = ? AND 
                            tr.close_time > 0 AND 
                            ((tr.cmd IN (0, 1) AND tr.comment NOT LIKE '%Summary trade result%'))
                      ORDER BY tr.close_time DESC
                      LIMIT {$limit} ");
            $statement->execute([$this->frsIDFrom, $number->value()]);
            $result = array_merge($result, $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE)));
        }

        return $result;
    }


    /**
     * @inheritdoc
     * @param AccountNumber $number
     * @param DateTime $from
     * @param DateTime $to
     * @return array
     */
    function getClosedOrders(AccountNumber $number, DateTime $from, DateTime $to): array
    {
        $tsFrom = $from->getTimestamp();
        $tsTo = $to->getTimestamp();

        $result = [];
        if($tsFrom < $this->migrationTimeStamp) {
            // fetch from frs mt4
            $statement = $this
                ->frsConnectionFrom
                ->prepare("
                    SELECT  
                         'frs' as `src`,
                         tr.order AS `ticket`, 
                         IF (tr.cmd = 0, 'BUY', 'SELL') AS `orderType`,
                         tr.volume AS `volume`,
                         tr.symbol AS `symbol`,
                         tr.open_time AS `openTime`, 
                         tr.open_price AS `openPrice`, 
                         tr.close_time AS `closeTime`, 
                         tr.close_price AS `closePrice`, 
                         tr.sl AS `stopLoss`, 
                         tr.tp AS `takeProfit`, 
                         tr.commission AS `commission`, 
                         tr.storage AS `swap`, 
                         tr.profit AS `profit`                    
                    FROM `mt4_trade_record` as tr 
                    WHERE 
                          tr.frs_RecOperation <> 'D' AND
                          tr.frs_ServerID = ? AND
                          tr.login = ? AND 
                          tr.cmd IN (0, 1) AND
                          tr.close_time BETWEEN ? AND ?
                ");
            $statement->execute([$this->frsIDFrom, $number->value(), $tsFrom, min($tsTo, $this->migrationTimeStamp)]);
            $result = array_merge($result, $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE)));
        }

        if($tsTo > $this->migrationTimeStamp) {
            // from MT5 frs
            $statement = $this
                ->frsConnectionTo
                ->prepare("
                    SELECT 

                        'frs' as `src`,
                   
                        ins.PositionID AS `ticket`,
                        IF(ins.Action = 0, 'BUY', 'SELL') AS `orderType`,
                        ROUND(outs.VolumeClosed / 10000, 4) AS `volume`,                       
                        ROUND(ins.Volume / 10000, 4) AS `size1`,	
                        ins.Symbol AS `symbol`,
                        ins.Time AS `openTime`,
                        ins.Price AS `openPrice`,                        
                        outs.Time AS `closeTime`,
                        outs.Price AS `closePrice`,
                        
                        ROUND((ins.Commission * (outs.VolumeClosed / ins.Volume) + outs.Commission), 4) AS `commission`,
                        ROUND(outs.Storage, 4) AS `swap`,
                        ROUND(outs.Profit, 4) AS `profit`

                    FROM `mt5_deal` AS ins
                        LEFT OUTER JOIN `mt5_deal` AS outs ON ins.PositionID = outs.PositionID AND ins.frs_ServerID = outs.frs_ServerID AND outs.Entry = 1 AND outs.frs_RecOperation <> 'D'                   
                    WHERE 
                        ins.frs_ServerID = ? AND 
                        ins.frs_RecOperation <> 'D' AND
                        ins.Login = ? AND
                        ins.Time >= ? AND ins.Time < ? AND 
                        ins.Action IN (0, 1) AND 
                        ins.Entry = 0 AND
                        outs.Deal IS NOT NULL
                    ORDER BY outs.Time DESC");
            $statement->execute(
                [$this->frsIDTo, $number->value(), max($tsFrom, $this->migrationTimeStamp), $tsTo]
            );
            $result = array_merge($result, $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE)));
        }
        return $result;
    }

    /**
     * @param AccountNumber $number
     * @return array
     * @throws DBALException
     */
    function getOpenOrders(AccountNumber $number): array
    {

        $statement = $this
            ->frsConnectionTo
            ->prepare("
                SELECT 
                       
                    'frs' as `src`,
                
                    ins.PositionID AS `ticket`,
                    IF(ins.Action = 0, 'BUY', 'SELL') AS `orderType`,
                    ROUND(ins.Volume / 10000, 4) AS `volume`,	
                    ins.Symbol AS `symbol`,              
                    ins.Time AS `openTime`,
                    ins.Price AS `openPrice`
                                    
                FROM `mt5_deal` AS ins
                    LEFT OUTER JOIN `mt5_deal` AS outs ON ins.PositionID = outs.PositionID AND ins.frs_ServerID = outs.frs_ServerID AND outs.Entry = 1 AND outs.frs_RecOperation <> 'D'                     
                WHERE
                    ins.frs_ServerID = ? AND
                    ins.frs_RecOperation <> 'D' AND
                    ins.Login = ? AND
                    ins.Action IN (0, 1) AND 
                    ins.Entry = 0 AND
                    outs.Deal IS NULL
                ORDER BY ins.Time ASC
            ");
        $statement->execute([$this->frsIDTo, $number->value()]);
        return $this->removeSymbolSuffix(
            $statement->fetchAll(FetchMode::ASSOCIATIVE)
        );
    }


    /**
     * Removes symbol suffixes
     * @param array $rows
     * @return array
     */
    private function removeSymbolSuffix(array $rows) : array
    {
        foreach ($rows as &$row) {
            if(isset($row['symbol'])) {
                $row['symbol'] = str_replace(['_EZc', '_ECc','_EZ', '_EC', '_T1c',], '', $row['symbol']);
            }
        }
        return $rows;
    }

}
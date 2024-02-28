<?php


namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\FetchMode;
use Fxtm\CopyTrading\Application\FollowerTradeHistory;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class FollowerTradeHistoryArsMigrated implements FollowerTradeHistory
{

    /**
     * @var Connection
     */
    private $arsConnection;

    /**
     * @var Connection
     */
    private $frsConnection;

    /**
     * @var int
     */
    private $frsID;

    /**
     * @var int
     */
    private $migrationTimeStamp;

    public function setArsConnection(Connection $connection): FollowerTradeHistoryArsMigrated
    {
        $this->arsConnection = $connection;
        return $this;
    }

    public function setFrsConnection(Connection $connection): FollowerTradeHistoryArsMigrated
    {
        $this->frsConnection = $connection;
        return $this;
    }

    public function setFrsId(int $id): FollowerTradeHistoryArsMigrated
    {
        $this->frsID = $id;
        return $this;
    }

    public function setMigrationDate(DateTime $dateTime): FollowerTradeHistoryArsMigrated
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getClosedOrdersCount(AccountNumber $number, DateTime $from, DateTime $to): int
    {
        $tsFrom = $from->getTimestamp();
        $tsTo = $to->getTimestamp();

        $count = 0;
        if($tsFrom < $this->migrationTimeStamp) {
            // fetch from ars
            $statement = $this
                ->arsConnection
                ->prepare("
                    SELECT count(*) 
                    FROM `orders` AS tr
                    WHERE 
                          tr.login = ? AND 
                          tr.cmd IN (0, 1) AND
                          tr.close_ts BETWEEN ? AND ?
                ");
            $statement->execute([$number->value(), $tsFrom, min($tsTo, $this->migrationTimeStamp)]);
            $count += intval($statement->fetchColumn(0));
        }

        if($tsTo > $this->migrationTimeStamp) {
            // from MT5 frs
            $statement = $this
                ->frsConnection
                ->prepare("
                SELECT count(*) FROM (
                    SELECT 
                        outs.PositionID AS `position`
                    FROM `mt5_deal` AS outs
                    WHERE 
                        outs.frs_ServerID = ? AND
                        outs.frs_RecOperation <> 'D' AND
                        outs.Login = ? AND
                        outs.Time >= ? AND outs.Time < ? AND 
                        outs.Action IN (0, 1) AND 
                        outs.Entry = 1 AND
						outs.Comment NOT LIKE 'CT add%'
                ) as frs
                ");
            $statement->execute(
                [$this->frsID, $number->value(), max($tsFrom, $this->migrationTimeStamp), $tsTo]
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getLatestClosedOrders(AccountNumber $number, int $limit): array
    {
        $result = [];

        // Firstly fetch from mt5
        $statement = $this
            ->frsConnection
            ->prepare("
                SELECT 
                       
                    'frs' as `src`,
                
                    outs.PositionID AS `ticket`,
                    IF(ins.Action = 0, 'BUY', 'SELL') AS `orderType`,
                    ROUND(outs.Volume / 10000, 4) AS `volume`,	
                    ins.Symbol AS `symbol`,
                    
                    ins.Time AS `openTime`,
                    ins.Price AS `openPrice`,
                    
                    outs.Time AS `closeTime`,
                    outs.Price AS `closePrice`,
                    
                    ROUND((ins.Commission * (outs.VolumeClosed / ins.Volume) + outs.Commission), 4) AS `commission`,
                    ROUND(outs.Storage, 4) AS `swap`,
                    ROUND(outs.Profit, 4) AS `profit`,
                    outs.Comment AS `comment`
                
                FROM `mt5_deal` AS outs
                    LEFT OUTER JOIN `mt5_deal` AS ins ON ins.PositionID = outs.PositionID AND ins.frs_ServerID = outs.frs_ServerID AND ins.Entry = 0 AND ins.frs_RecOperation <> 'D'
                WHERE
                    outs.frs_ServerID = ? AND
                    outs.frs_RecOperation <> 'D' AND
                    outs.Login = ? AND
                    outs.Action IN (0, 1) AND 
                    outs.Entry = 1 AND
                    outs.Comment NOT LIKE 'CT add%'
                
                ORDER BY ins.Time DESC
                LIMIT {$limit} 
            ");
        $statement->execute([$this->frsID, $number->value()]);
        $result = array_merge($result, $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE)));

        // Secondly if limit is not reached try fetch more data from ars mt4
        if(count($result) < $limit) {
            $limit -= count($result);
            $statement = $this
                ->arsConnection
                ->prepare("
                      SELECT
                         'ars' as `src`,
                         tr.order AS ticket, 
                         IF (tr.cmd = 0, 'BUY', 'SELL') AS `orderType`,
                         tr.volume AS `volume`,
                         tr.symbol64 AS `symbol`,
                         tr.open_ts AS `openTime`, 
                         tr.open_price AS `openPrice`, 
                         tr.close_ts AS `closeTime`, 
                         tr.close_price AS `closePrice`, 
                         tr.sl AS `stopLoss`, 
                         tr.tp AS `takeProfit`, 
                         tr.commission AS `commission`, 
                         tr.storage AS `swap`, 
                         tr.profit AS `profit`
                      FROM `orders` AS tr
                      WHERE
                            tr.login = ? AND 
                            tr.close_ts > 0 AND 
                            ((tr.cmd IN (0, 1) AND tr.comment NOT LIKE '%Summary trade result%'))
                      ORDER BY tr.close_ts DESC
                      LIMIT {$limit} ");
            $statement->execute([$number->value()]);
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    function getClosedOrders(AccountNumber $number, DateTime $from, DateTime $to): array
    {
        $tsFrom = $from->getTimestamp();
        $tsTo = $to->getTimestamp();

        $result = [];
        if($tsFrom < $this->migrationTimeStamp) {
            // fetch from ars mt4
            $statement = $this
                ->arsConnection
                ->prepare("
                    SELECT  
                         'ars' as `src`,
                         tr.order AS `ticket`, 
                         IF (tr.cmd = 0, 'BUY', 'SELL') AS `orderType`,
                         tr.volume AS `volume`,
                         tr.symbol64 AS `symbol`,
                         tr.open_ts AS `openTime`, 
                         tr.open_price AS `openPrice`, 
                         tr.close_ts AS `closeTime`, 
                         tr.close_price AS `closePrice`, 
                         tr.sl AS `stopLoss`, 
                         tr.tp AS `takeProfit`, 
                         tr.commission AS `commission`, 
                         tr.storage AS `swap`, 
                         tr.profit AS `profit`                    
                    FROM `orders` as tr 
                    WHERE 
                          tr.login = ? AND 
                          tr.cmd IN (0, 1) AND
                          tr.close_ts BETWEEN ? AND ?
                ");
            $statement->execute([$number->value(), $tsFrom, min($tsTo, $this->migrationTimeStamp)]);
            $result = array_merge($result, $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE)));
        }

        if($tsTo > $this->migrationTimeStamp) {
            // from MT5 frs
            $statement = $this
                ->frsConnection
                ->prepare("
                    SELECT 
                           
                        'frs' as `src`,
                    
                        outs.PositionID AS `ticket`,
                        IF(ins.Action = 0, 'BUY', 'SELL') AS `orderType`,
                        ROUND(outs.Volume / 10000, 4) AS `volume`,	
                        ins.Symbol AS `symbol`,
                        
                        ins.Time AS `openTime`,
                        ins.Price AS `openPrice`,
                        
                        outs.Time AS `closeTime`,
                        outs.Price AS `closePrice`,
                        
                        ROUND((ins.Commission * (outs.VolumeClosed / ins.Volume) + outs.Commission), 4) AS `commission`,
                        ROUND(outs.Storage, 4) AS `swap`,
                        ROUND(outs.Profit, 4) AS `profit`,
                        outs.Comment AS `comment`
                    
                    FROM `mt5_deal` AS outs
                        LEFT OUTER JOIN `mt5_deal` AS ins ON ins.PositionID = outs.PositionID AND ins.frs_ServerID = outs.frs_ServerID AND ins.Entry = 0 AND ins.frs_RecOperation <> 'D'
                    WHERE
                        outs.frs_ServerID = ? AND
                        outs.frs_RecOperation <> 'D' AND
                        outs.Login = ? AND
                        outs.Action IN (0, 1) AND 
                        outs.Entry = 1 AND
                        outs.Time >= ? AND outs.Time < ? AND 
                        outs.Comment NOT LIKE 'CT add%'
                    
                    ORDER BY ins.Time DESC
                ");
            $statement->execute(
                [$this->frsID, $number->value(), max($tsFrom, $this->migrationTimeStamp), $tsTo]
            );
            $result = array_merge($result, $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE)));
        }
        return $result;
    }

    /**
     * @param AccountNumber $number
     * @return array
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    function getOpenOrders(AccountNumber $number): array
    {

        $statement = $this
            ->frsConnection
            ->prepare("
                SELECT 
                       
                    'frs' as `src`,
                
                    ins.PositionID AS `ticket`,
                    IF(ins.Action = 0, 'BUY', 'SELL') AS `orderType`,
                    ROUND(ins.Volume / 10000, 4) - COALESCE(SUM(ROUND(outs.Volume / 10000, 4)), 0) AS `volume`,	
                    ins.Symbol AS `symbol`,              
                    ins.Time AS `openTime`,
                    ins.Price AS `openPrice`,
					ROUND(ins.Volume / 10000, 4) AS `ins_volume`,	
                    COALESCE(SUM(ROUND(outs.Volume / 10000, 4)), 0) AS `outs_volume`
                                    
                FROM `mt5_deal` AS ins
                    LEFT OUTER JOIN `mt5_deal` AS outs ON ins.PositionID = outs.PositionID AND ins.frs_ServerID = outs.frs_ServerID AND outs.Entry = 1 AND outs.frs_RecOperation <> 'D'                     
                WHERE
                    ins.frs_ServerID = ? AND
                    ins.frs_RecOperation <> 'D' AND
                    ins.Login = ? AND
                    ins.Action IN (0, 1) AND 
                    ins.Entry = 0

                GROUP BY ins.PositionID

				HAVING
                    ins_volume != outs_volume
                    
                ORDER BY ins.Time ASC
            ");
        $statement->execute([$this->frsID, $number->value()]);
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
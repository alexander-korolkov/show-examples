<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;


use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\FetchMode;
use Fxtm\CopyTrading\Application\FollowerTradeHistory;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class FollowerTradeHistoryFrs implements FollowerTradeHistory
{

    /**
     * @var Connection
     */
    private $frsConnection;

    /**
     * @var int
     */
    private $frsId;

    /**
     * @param Connection $connection
     * @return $this
     */
    public function setFrsConnection(Connection $connection): FollowerTradeHistoryFrs
    {
        $this->frsConnection = $connection;
        return $this;
    }

    /**
     * @param int $id Frs server ID
     * @return $this
     */
    public function setFrsId(int $id): FollowerTradeHistoryFrs
    {
        $this->frsId = $id;
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
        $statement->execute([$this->frsId, $number->value(), $tsFrom, $tsTo]);
        return intval($statement->fetchColumn(0));
    }

    /**
     * @inheritdoc
     * @param AccountNumber $number
     * @param int $limit
     * @return array
     */
    public function getLatestClosedOrders(AccountNumber $number, int $limit): array
    {
        // Fetch only from mt5
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
        $statement->execute([$this->frsId, $number->value()]);
        return $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE));
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

        $statement->execute([$this->frsId, $number->value(), $tsFrom, $tsTo]);

        return $this->removeSymbolSuffix($statement->fetchAll(FetchMode::ASSOCIATIVE));
    }

    /**
     * @param AccountNumber $number
     * @return array
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

                ORDER BY ins.Time DESC
            ");
        $statement->execute([$this->frsId, $number->value()]);
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
                $row['symbol'] = str_replace(['_EZc', '_ECc','_EZ', '_EC',], '', $row['symbol']);
            }
        }
        return $rows;
    }

}
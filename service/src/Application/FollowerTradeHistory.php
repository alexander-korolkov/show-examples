<?php


namespace Fxtm\CopyTrading\Application;


use Doctrine\DBAL\DBALException;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface FollowerTradeHistory
{

    /**
     * Returns count of closed positions for specified account and date range.
     * Moved to separated entity because trading history for some accounts stored both on 2 different servers
     *
     * @param AccountNumber $number
     * @param DateTime $from
     * @param DateTime $to
     * @return int
     * @throws DBALException
     */
    function getClosedOrdersCount(AccountNumber $number, DateTime $from, DateTime $to) : int;

    /**
     * Returns recently closed orders limited to $limit
     *
     * @param AccountNumber $number
     * @param int $limit
     * @return array
     * @throws DBALException
     */
    function getLatestClosedOrders(AccountNumber $number, int $limit) : array;

    /**
     * Returns closed positions for specified account and date range.
     * Moved to separated entity because trading history for some accounts stored both on 2 different servers
     *
     * @param AccountNumber $number
     * @param DateTime $from
     * @param DateTime $to
     * @return array
     * @throws DBALException
     */
    function getClosedOrders(AccountNumber $number, DateTime $from, DateTime $to) : array;

    /**
     * Returns array of open orders for account number
     *
     * @param AccountNumber $number
     * @return array
     */
    function getOpenOrders(AccountNumber  $number) : array;

}
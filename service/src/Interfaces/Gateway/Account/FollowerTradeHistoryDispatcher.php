<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Fxtm\CopyTrading\Application\FollowerTradeHistory;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Persistence\MetaData\ServerIdentification;
use Fxtm\CopyTrading\Interfaces\Repository\MetaDataRepository;
use \InvalidArgumentException;

class FollowerTradeHistoryDispatcher implements FollowerTradeHistory
{

    /**
     * @var MetaDataRepository
     */
    private $metaDataRepository;

    /**
     * @var Connection
     */
    private $frsFXTMConnection;

    /**
     * @var Connection
     */
    private $frsAINTConnection;


    /**
     * @param MetaDataRepository $repository
     */
    public function setMetaDataRepository(MetaDataRepository $repository)
    {
        $this->metaDataRepository = $repository;
    }

    /**
     * @param Connection $connection
     */
    public function setFrsFXTMConnection(Connection $connection)
    {
        $this->frsFXTMConnection = $connection;
    }

    /**
     * @param Connection $connection
     */
    public function setFrsAINTConnection(Connection $connection)
    {
        $this->frsAINTConnection = $connection;
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    function getClosedOrdersCount(AccountNumber $number, DateTime $from, DateTime $to): int
    {
        return $this->getImplementation($number)->getClosedOrdersCount($number, $from, $to);
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    function getLatestClosedOrders(AccountNumber $number, int $limit): array
    {
        return $this->getImplementation($number)->getLatestClosedOrders($number, $limit);
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    function getClosedOrders(AccountNumber $number, DateTime $from, DateTime $to): array
    {
        return $this->getImplementation($number)->getClosedOrders($number, $from, $to);
    }

    function getOpenOrders(AccountNumber $number): array
    {
        return $this->getImplementation($number)->getOpenOrders($number);
    }

    private function getImplementation(AccountNumber $number): FollowerTradeHistory
    {
        $metaData = $this->metaDataRepository->getMetaData($number);
        if(!$metaData->isFollower()) {
            throw new \ValueError("{$number->value()} is not follower's account number");
        }
        if($metaData->isMigrated()) {
            //TODO I have return ARS implementation because FRS cannot be used for getting MT4 trading history (shrinker)
            return (new FollowerTradeHistoryArsMigrated())
                ->setMigrationDate($metaData->getMigrationDate())
                ->setArsConnection($metaData->getDataSourceComponent()->getARSConnection())
                ->setFrsConnection($this->frsFXTMConnection)
                ->setFrsId($metaData->getFrsMt5ServerId());
//            return (new FollowerTradeHistoryFrsMigrated())
//                ->setMigrationDate($metaData->getMigrationDate())
//                ->setFrsConnectionFrom($metaData->getDataSourceComponent()->getFRSConnection())
//                ->setFrsIdFrom($metaData->getFrsMt4ServerId())
//                ->setFrsConnectionTo($this->frsFXTMConnection)
//                ->setFrsIdTo($metaData->getFrsMt5ServerId());
        }
        return (new FollowerTradeHistoryFrs())
            ->setFrsConnection($metaData->getDataSourceComponent()->getFRSConnection())
            ->setFrsId($metaData->getFrsMt5ServerId());
    }

}
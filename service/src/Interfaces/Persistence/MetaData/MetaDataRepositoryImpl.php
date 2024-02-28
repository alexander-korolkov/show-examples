<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\MetaData;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Entity\MetaData\MetaData;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\Repository\MetaDataRepository;
use LogicException;
use Psr\Log\LoggerInterface;

class MetaDataRepositoryImpl implements MetaDataRepository
{

    /**
     * @var int
     */
    private $fxtmEcnFrsServerId;

    /**
     * @var int
     */
    private $fxtmAdvantageEcnFrsServerId;

    /**
     * @var int
     */
    private $fxtmEcnZeroFrsServerId;

    /**
     * @var int
     */
    private $aintEcnFrsServerId;

    /**
     * @var int
     */
    private $fxtmMt5FrsServerId;

    /**
     * @var int
     */
    private $aintMt5FrsServerId;

    /**
     * @var int
     */
    private $aintEcnMt5FrsServerId;

    /**
     * @var DataSourceFactory
     */
    private $dsFactory;

    /**
     * @var DateTime
     */
    private $ecnAIMigrationDate;

    /**
     * @var DateTime
     */
    private $ecnFXTMMigrationDate;

    /**
     * @var DateTime
     */
    private $ecnZeroFXTMMigrationDate;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Finds metadata for trading account
     *
     * @param AccountNumber $account
     * @return MetaData
     */
    public function getMetaData(AccountNumber $account): MetaData
    {
        $result = new MetaData();
        $identifier = ServerIdentification::classify(intval($account->value()));

        $result->setBroker($identifier->getBroker());
        $result->setServerId($identifier->getServerId());
        $result->setTradingPlatformVersion($identifier->getTradingPlatformVersion());

        switch ($identifier->getServerId()) {
            case Server::ECN:
                if ($identifier->isFollower()) {
                    $result->setIsFollower(true);
                    if ($identifier->getBroker() == Broker::ALPARI) {
                        // AINT follower ecn
                        $result->setFrsMt5ServerId($this->aintMt5FrsServerId);
                        if ($identifier->getTradingPlatformVersion() == ServerIdentification::MT_4) {
                            $result->setIsMigrated(true);
                            $result->setMigrationDate($this->ecnAIMigrationDate);
                            $result->setFrsMt4ServerId($this->aintEcnFrsServerId);
                            $result->setArsMt4ServerId(Server::AI_ECN);
                            $result->setServerId(Server::MT5_AINT);
                            $result->setTradingPlatformVersion(ServerIdentification::MT_5);
                        }
                    } else if ($identifier->getBroker() == Broker::FXTM) {
                        // FXTM follower ecn
                        $result->setFrsMt5ServerId($this->fxtmMt5FrsServerId);
                        if ($identifier->getTradingPlatformVersion() == ServerIdentification::MT_4) {
                            $result->setIsMigrated(true);
                            $result->setMigrationDate($this->ecnFXTMMigrationDate);
                            $result->setFrsMt4ServerId($this->fxtmEcnFrsServerId);
                            $result->setArsMt4ServerId(Server::ECN);
                            $result->setServerId(Server::MT5_FXTM);
                            $result->setTradingPlatformVersion(ServerIdentification::MT_5);
                        }
                    } else {
                        // ABY follower ecn
                        $result->setFrsMt5ServerId($this->aintMt5FrsServerId);
                    }
                } else {
                    $result->setIsLeader(!$identifier->isAggregator());
                    if ($identifier->getBroker() == Broker::ALPARI) {
                        if ($identifier->getTradingPlatformVersion() == ServerIdentification::MT_5) {
                            $result->setFrsMt5ServerId($this->aintEcnMt5FrsServerId);
                        } else {
                            $result->setFrsMt4ServerId($this->aintEcnFrsServerId);
                        }
                    } else if ($identifier->getBroker() == Broker::FXTM) {
                        if ($identifier->getTradingPlatformVersion() == ServerIdentification::MT_5) {
                            $result->setFrsMt5ServerId($this->fxtmMt5FrsServerId);
                        } else {
                            $result->setFrsMt4ServerId($this->fxtmEcnFrsServerId);
                        }
                    } else {
                        $result->setFrsMt4ServerId($this->aintEcnFrsServerId);
                    }
                }
                break;

            case Server::ECN_ZERO:
                if ($identifier->isFollower()) {
                    $result->setIsFollower(true);
                    if ($identifier->getBroker() == Broker::ALPARI) {
                        // AINT follower ecn zero
                        if ($identifier->getTradingPlatformVersion() == ServerIdentification::MT_4) {
                            $result->setIsMigrated(true);
                            $result->setMigrationDate($this->ecnAIMigrationDate);
                            $result->setFrsMt5ServerId($this->aintMt5FrsServerId);
                            $result->setFrsMt4ServerId($this->aintEcnFrsServerId);
                            $result->setArsMt4ServerId(Server::ECN_ZERO);
                            $result->setServerId(Server::MT5_AINT);
                            $result->setTradingPlatformVersion(ServerIdentification::MT_5);
                        } else {
                            throw new LogicException("Impossible state; AI ecn zero does not exist");
                        }
                    } else if ($identifier->getBroker() == Broker::FXTM) {
                        // FXTM follower ecn zero
                        if ($identifier->getTradingPlatformVersion() == ServerIdentification::MT_4) {
                            $result->setIsMigrated(true);
                            $result->setFrsMt5ServerId($this->fxtmMt5FrsServerId);
                            $result->setMigrationDate($this->ecnZeroFXTMMigrationDate);
                            $result->setFrsMt4ServerId($this->fxtmEcnZeroFrsServerId);
                            $result->setArsMt4ServerId(Server::ECN_ZERO);
                            $result->setServerId(Server::MT5_FXTM);
                            $result->setTradingPlatformVersion(ServerIdentification::MT_5);
                        } else {
                            $result->setFrsMt5ServerId($this->fxtmMt5FrsServerId);
                        }
                    } else {
                        // ABY follower ecn zero
                        $result->setFrsMt5ServerId($this->aintMt5FrsServerId);
                    }
                } else {
                    $result->setIsLeader(!$identifier->isAggregator());
                    if ($identifier->getBroker() == Broker::ALPARI) {
                        throw new LogicException("Impossible state; AI ecn zero does not exist on MT5");
                    } else if ($identifier->getBroker() == Broker::FXTM) {
                        if ($identifier->getTradingPlatformVersion() == ServerIdentification::MT_5) {
                            $result->setFrsMt5ServerId($this->fxtmMt5FrsServerId);
                        } else {
                            $result->setFrsMt4ServerId($this->fxtmEcnZeroFrsServerId);
                        }
                    } else {
                        $result->setFrsMt4ServerId($this->aintEcnFrsServerId);
                    }
                }
                break;

            case Server::ADVANTAGE_ECN:
                $result->setIsLeader(!$identifier->isAggregator());
                $result->setFrsMt5ServerId($this->fxtmAdvantageEcnFrsServerId);
                break;

            case Server::MT5_AI_ECN:
                $result->setIsLeader(!$identifier->isAggregator());
                $result->setFrsMt5ServerId($this->aintEcnMt5FrsServerId);
                break;

            default:
                throw new LogicException("Trade account is not related to copy-trading");
        }

        $result->setDataSourceComponent($this->dsFactory->bake($result));

        $this->logger->info("[MetaData {$account}] {$result}");

        return $result;
    }

    /**
     * @param DataSourceFactory $factory
     */
    public function setDataSourceFactory(DataSourceFactory $factory): void
    {
        $this->dsFactory = $factory;
    }

    /**
     * @param string $ecnAIMigrationDate
     */
    public function setEcnAIMigrationDate(string $ecnAIMigrationDate): void
    {
        $this->ecnAIMigrationDate = DateTime::of($ecnAIMigrationDate);
    }

    /**
     * @param string $ecnFXTMMigrationDate
     */
    public function setEcnFXTMMigrationDate(string $ecnFXTMMigrationDate): void
    {
        $this->ecnFXTMMigrationDate = DateTime::of($ecnFXTMMigrationDate);
    }

    /**
     * @param string $ecnZeroFXTMMigrationDate
     */
    public function setEcnZeroFXTMMigrationDate(string $ecnZeroFXTMMigrationDate): void
    {
        $this->ecnZeroFXTMMigrationDate = DateTime::of($ecnZeroFXTMMigrationDate);
    }

    /**
     * @param int|string $fxtmEcnFrsServerId
     */
    public function setFxtmEcnFrsServerId($fxtmEcnFrsServerId): void
    {
        $this->fxtmEcnFrsServerId = intval($fxtmEcnFrsServerId);
    }

    /**
     * @param int|string $fxtmAdvantageEcnFrsServerId
     */
    public function setFxtmAdvantageEcnFrsServerId($fxtmAdvantageEcnFrsServerId): void
    {
        $this->fxtmAdvantageEcnFrsServerId = intval($fxtmAdvantageEcnFrsServerId);
    }

    /**
     * @param int|string $fxtmEcnZeroFrsServerId
     */
    public function setFxtmEcnZeroFrsServerId($fxtmEcnZeroFrsServerId): void
    {
        $this->fxtmEcnZeroFrsServerId = intval($fxtmEcnZeroFrsServerId);
    }

    /**
     * @param int|string $aintEcnFrsServerId
     */
    public function setAintEcnFrsServerId($aintEcnFrsServerId): void
    {
        $this->aintEcnFrsServerId = intval($aintEcnFrsServerId);
    }

    /**
     * @param int|string $fxtmMt5FrsServerId
     */
    public function setFxtmMt5FrsServerId($fxtmMt5FrsServerId): void
    {
        $this->fxtmMt5FrsServerId = intval($fxtmMt5FrsServerId);
    }

    /**
     * @param int|string $aintMt5FrsServerId
     */
    public function setAintMt5FrsServerId($aintMt5FrsServerId): void
    {
        $this->aintMt5FrsServerId = intval($aintMt5FrsServerId);
    }

    /**
     * @param int|string $aintEcnMt5FrsServerId
     */
    public function setAintEcnMt5FrsServerId($aintEcnMt5FrsServerId): void
    {
        $this->aintEcnMt5FrsServerId = intval($aintEcnMt5FrsServerId);
    }
}

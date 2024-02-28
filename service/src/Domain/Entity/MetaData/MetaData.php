<?php


namespace Fxtm\CopyTrading\Domain\Entity\MetaData;

use Fxtm\CopyTrading\Domain\Common\DateTime;

class MetaData
{

    /**
     * @var DataSourceComponent
     */
    private $dataSource;

    /**
     * @var bool
     */
    private $isMigrated = false;

    /**
     * @var DateTime
     */
    private $migrationDate;

    /**
     * @var int
     */
    private $frsMt4ServerId;

    /**
     * @var int
     */
    private $arsMt4ServerId;

    /**
     * @var int
     */
    private $frsMt5ServerId;

    /**
     * @var int
     */
    private $serverId;

    /**
     * @var string
     */
    private $broker;

    /**
     * @var int
     */
    private $tpVersion;

    /**
     * @var boolean
     */
    private $isFollower = false;

    /**
     * @var boolean
     */
    private $isLeader = false;


    /**
     * @return DataSourceComponent
     */
    public function getDataSourceComponent(): DataSourceComponent
    {
        return $this->dataSource;
    }

    /**
     * @param DataSourceComponent $dataSourceComponent
     */
    public function setDataSourceComponent(DataSourceComponent $dataSourceComponent): void
    {
        $this->dataSource = $dataSourceComponent;
    }

    /**
     * @return bool
     */
    public function isMigrated(): bool
    {
        return $this->isMigrated;
    }

    /**
     * @param bool $isMigrated
     */
    public function setIsMigrated(bool $isMigrated): void
    {
        $this->isMigrated = $isMigrated;
    }

    /**
     * @return DateTime
     */
    public function getMigrationDate(): DateTime
    {
        return $this->migrationDate;
    }

    /**
     * @param DateTime $migrationDate
     */
    public function setMigrationDate(DateTime $migrationDate): void
    {
        $this->migrationDate = $migrationDate;
    }

    /**
     * @return int
     */
    public function getFrsMt4ServerId(): int
    {
        return $this->frsMt4ServerId;
    }

    /**
     * @param int $frsMt4ServerId
     */
    public function setFrsMt4ServerId(int $frsMt4ServerId): void
    {
        $this->frsMt4ServerId = $frsMt4ServerId;
    }

    public function getArsMt4ServerId(): int
    {
        return $this->arsMt4ServerId;
    }

    /**
     * @param int $arsMt4ServerId
     */
    public function setArsMt4ServerId(int $arsMt4ServerId): void
    {
        $this->arsMt4ServerId = $arsMt4ServerId;
    }

    /**
     * @return int
     */
    public function getFrsMt5ServerId(): int
    {
        return $this->frsMt5ServerId;
    }

    /**
     * @param int $frsMt5ServerId
     */
    public function setFrsMt5ServerId(int $frsMt5ServerId): void
    {
        $this->frsMt5ServerId = $frsMt5ServerId;
    }

    /**
     * @return int
     */
    public function getServerId(): int
    {
        return $this->serverId;
    }

    /**
     * @param int $serverId
     */
    public function setServerId(int $serverId): void
    {
        $this->serverId = $serverId;
    }

    /**
     * @return string
     */
    public function getBroker(): string
    {
        return $this->broker;
    }

    /**
     * @param string $broker
     */
    public function setBroker(string $broker): void
    {
        $this->broker = $broker;
    }

    /**
     * @return int
     */
    public function getTradingPlatformVersion(): int
    {
        return $this->tpVersion;
    }

    /**
     * @param int $tpVersion
     */
    public function setTradingPlatformVersion(int $tpVersion): void
    {
        $this->tpVersion = $tpVersion;
    }

    /**
     * @return bool
     */
    public function isFollower(): bool
    {
        return $this->isFollower;
    }

    /**
     * @param bool $isFollower
     */
    public function setIsFollower(bool $isFollower): void
    {
        $this->isFollower = $isFollower;
    }

    /**
     * @return bool
     */
    public function isLeader(): bool
    {
        return $this->isLeader;
    }

    /**
     * @param bool $isLeader
     */
    public function setIsLeader(bool $isLeader): void
    {
        $this->isLeader = $isLeader;
    }

    public function __toString() : string
    {
        return sprintf(
            "MetaData{\nisMigrated = %s\nmigrationDate = %s\nfrsMt4ServerId = %s\narsMt4ServerId = %s\nfrsMt5ServerId = %s\nserverId = %s\nbroker = %s\ntpVersion = %s\nisFollower = %s\nisLeader = %s\n}",
            $this->isMigrated,
            $this->migrationDate,
            $this->frsMt4ServerId,
            $this->arsMt4ServerId,
            $this->frsMt5ServerId,
            $this->serverId,
            $this->broker,
            $this->tpVersion,
            $this->isFollower,
            $this->isLeader
        );
    }


}
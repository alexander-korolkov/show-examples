<?php


namespace Fxtm\CopyTrading\Domain\Entity\MetaData;


use Doctrine\DBAL\Connection;

/**
 * Class TradingAccountMetaDataSourceComponent
 * @package Fxtm\CopyTrading\Domain\Entity\MetaData
 */
class DataSourceComponent
{

    /**
     * @var Connection
     */
    private $myConnection;

    /**
     * @var Connection
     */
    private $sasConnection;

    /**
     * @var Connection
     */
    private $ctConnection;

    /**
     * @var Connection
     */
    private $pluginConnection;

    /**
     * @var Connection|null
     */
    private $frsPreviousConnection;

    /**
     * @var Connection
     */
    private $frsConnection;

    /**
     * @var Connection
     */
    private $arsConnection;

    public function __construct(
        Connection $myConnection,
        Connection $sasConnection,
        Connection $ctConnection,
        Connection $pluginConnection,
        ?Connection $frsPreviousConnection,
        Connection $frsConnection,
        ?Connection $arsConnection
    )
    {
        $this->myConnection             = $myConnection;
        $this->sasConnection            = $sasConnection;
        $this->ctConnection             = $ctConnection;
        $this->pluginConnection         = $pluginConnection;
        $this->frsPreviousConnection    = $frsPreviousConnection;
        $this->frsConnection            = $frsConnection;
        $this->arsConnection            = $arsConnection;
    }

    public function getMYConnection(): Connection
    {
        return $this->myConnection;
    }

    public function getSASConnection(): Connection
    {
        return $this->sasConnection;
    }

    public function getCTConnection(): Connection
    {
        return $this->ctConnection;
    }

    public function getPreviousFRSConnection(): ?Connection
    {
        return $this->frsPreviousConnection;
    }

    public function getFRSConnection(): Connection
    {
        return $this->frsConnection;
    }

    public function getARSConnection(): ?Connection
    {
        return $this->arsConnection;
    }

    public function getPluginConnection(): Connection
    {
        return $this->pluginConnection;
    }

}
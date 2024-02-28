<?php

namespace Fxtm\CopyTrading\Interfaces\API;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Utils\VersioningUtils;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthChecksController
{
    /**
     * @var DataSourceFactory
     */
    private $dataSourceFactory;

    /**
     * @var VersioningUtils
     */
    private $versioningUtils;

    /**
     * HealthChecksController constructor.
     * @param VersioningUtils $versioningUtils
     */
    public function __construct(
        DataSourceFactory $dataSourceFactory,
        VersioningUtils $versioningUtils
    ) {
        $this->versioningUtils = $versioningUtils;
        if (!MetrixData::getWorker()) {
            MetrixData::setWorker('WEB[HEALTH]');
        }
        $this->dataSourceFactory = $dataSourceFactory;
    }


    /**
     * @Route(path="/health", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function check() : JsonResponse
    {
        $connections = [
            'copy_trading_conn' => $this->dataSourceFactory->getCTConnection(),
            'fxtm_my_conn' => $this->dataSourceFactory->getMyConnection(Broker::FXTM),
            'aint_my_conn' => $this->dataSourceFactory->getMyConnection(Broker::ALPARI),
            'fxtm_sas_conn' => $this->dataSourceFactory->getSasConnection(Broker::FXTM),
            'aint_sas_conn' => $this->dataSourceFactory->getSasConnection(Broker::ALPARI),
            'ars_ecn_conn' => $this->dataSourceFactory->getArsConnection(Server::ECN),
            'ars_ecn_zero_conn' => $this->dataSourceFactory->getArsConnection(Server::ECN_ZERO),
            'ars_ai_ecn_conn' => $this->dataSourceFactory->getArsConnection(Server::AI_ECN),
            'ars_advantage_ecn_conn' => $this->dataSourceFactory->getArsConnection(Server::ADVANTAGE_ECN),
            'frs_ecn_conn' => $this->dataSourceFactory->getFrsConnection(Server::ECN),
            'frs_ecn_zero_conn' => $this->dataSourceFactory->getFrsConnection(Server::ECN_ZERO),
            'frs_ai_ecn_conn' => $this->dataSourceFactory->getFrsConnection(Server::AI_ECN),
            'frs_advantage_ecn_conn' => $this->dataSourceFactory->getFrsConnection(Server::ADVANTAGE_ECN),
            'frs_ai_mt5_ecn_conn' => $this->dataSourceFactory->getFrsConnection(Server::MT5_AI_ECN),
            'frs_mt5_fxtm_conn' => $this->dataSourceFactory->getFrsConnection(Server::MT5_FXTM),
            'frs_mt5_aint_conn' => $this->dataSourceFactory->getFrsConnection(Server::MT5_AINT),
        ];

        $connections = array_merge($connections, $this->dataSourceFactory->getAllPluginConnections());

        $data = [];
        foreach ($connections as $connName => $connection) {
            $data[$connName] = $this->checkDbConnection($connection);
        }

        $exitStatus = 0;
        $status = 'OK UP';
        foreach ($data as $dbStatus) {
            if (strpos($dbStatus, 'failed') !== false) {
                $exitStatus = 2;
                $status = 'CRITICAL DOWN/UNREACHABLE';
            }
        }

        return new JsonResponse(
            [
                'exit_status' => $exitStatus,
                'status' => $status,
                'performance_data' => $data,
            ],
            200,
            ['App-Version' => $this->versioningUtils->getCurrentVersion()]
        );
    }

    /**
     * Method makes attempt to connect to all dbs,
     * and measures connection timeout
     *
     * @param Connection $dbConnection
     *
     * @return string
     */
    private function checkDbConnection(Connection $dbConnection) : string
    {
        $startTime = microtime(true);

        try {
            $res = $dbConnection->query('SELECT 1')->fetch();
            if (!$res) {
                throw new \Exception('SELECT 1 query returned something else.');
            }
        } catch (\Exception $e) {
            return "failed;{$e->getMessage()}";
        }

        $connectionTimeout = microtime(true) - $startTime;

        return "success;time={$connectionTimeout}ms";
    }
}

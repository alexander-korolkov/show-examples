<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Fxtm\CopyTrading\Application\Common\Environment;
use Fxtm\CopyTrading\Application\Leader\EnableCopyingWorkflow;
use Fxtm\CopyTrading\Interfaces\DbConnector\FrsConnector;
use Fxtm\TwGateClient\TeamWoxService;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class NewActiveLeadersObserverCommand extends Command
{

    use LockableTrait;

    /**
     * @var Connection
     */
    private $dbConnection;

    /**
     * @var FrsConnector
     */
    private $frsConnector;

    /**
     * @var TeamWoxService
     */
    private $teamwoxService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Connection $dbConnection
     */
    public function setDbConnection(Connection $dbConnection): void
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * @param FrsConnector $connector
     */
    public function setFrsConnector(FrsConnector $connector): void
    {
        $this->frsConnector = $connector;
    }

    /**
     * @param TeamWoxService $service
     */
    public function setTeamwoxService(TeamWoxService $service): void
    {
        $this->teamwoxService = $service;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:new_active_leaders:observe')
            ->setDescription('Sends messages to teamwox about new active leaders');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if(!Environment::isProd()) {
            $this->logger->error(
                self::fmt('Invalid environment.')
            );
            return 0;
        }

        $this->logger->info(
            self::fmt('Process started.')
        );

        if(!$this->lock()) {
            $output->writeln("Another process is running.");
            $this->logger->error(
                self::fmt("Failed to set lock, another process is running.")
            );
            return -1;
        }

        $leaders = $this->getNewActiveLeaders();
        if (count($leaders) == 0) {
            $this->logger->info(
                self::fmt('Found 0 new leaders, process finished.')
            );
            return 0;
        }

        $this->logger->info(
            self::fmt('Found %d new leaders.', [count($leaders)])
        );

        foreach ($leaders as &$leader) {
            $leader = $this->enrichWithFrsData($leader);
        }

        $this->sendMessageToTeamwox($leaders);

        $this->release();

        $this->logger->info(
            self::fmt('Process finished.')
        );

        return 0;
    }

    /**
     * @return array
     */
    private function getNewActiveLeaders() : array
    {
        try {
            $stmt = $this->dbConnection->prepare("
                SELECT
                    la.acc_no      lead_acc_no,
                    la.server      lead_acc_server,
                    la.aggr_acc_no aggr_acc_no,
                    fa.acc_no      foll_acc_no,
                    fa.opened_at   foll_acc_opened_at                    
                FROM workflows          w
                JOIN activities         a ON a.workflow_id = w.id AND a.name = 'createAggregateAccount' AND a.state = 4
                JOIN leader_accounts   la ON la.acc_no = w.corr_id
                JOIN workflows          p ON p.id = w.parent_id
                JOIN follower_accounts fa ON fa.acc_no = p.corr_id
                WHERE
                    w.state = 2 AND
                    w.finished_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND
                    w.type = :enable_copying_workflow_type
            ");

            $stmt->execute([
                'enable_copying_workflow_type' => EnableCopyingWorkflow::TYPE,
            ]);

            $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (DBALException $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

            $leaders = [];
        }

        return $leaders;
    }

    /**
     * @param array $leader
     * @return array
     */
    private function enrichWithFrsData(array $leader): array
    {
        $leaderData = $aggregatorData = [];
        try {
            $connection = $this->frsConnector->getConnection($leader['lead_acc_server']);

            $stmt = $connection->prepare("
                SELECT
                    la.group      AS lead_acc_group,
                    la.id         AS lead_acc_id,
                    po.hedge_rate AS lead_acc_coeff
                FROM mt4_user_record AS la
                LEFT JOIN (
                    SELECT
                        ROUND(tr.gw_volume / tr.volume, 4) AS hedge_rate
                    FROM mt4_trade_record AS tr
                    WHERE tr.login = :acc_no AND tr.gw_volume > 0.0 AND tr.frs_RecOperation <> 'D'
                    LIMIT 1
                ) po ON 1
                WHERE la.login = :acc_no AND la.frs_RecOperation <> 'D'
            ");

            $stmt->execute([
                'acc_no' => $leader['lead_acc_no'],
            ]);

            $leaderData = $stmt->fetch(PDO::FETCH_ASSOC);
            if($leaderData === false) {
                $leaderData = [
                    'lead_acc_group' => '',
                    'lead_acc_id' => '',
                    'lead_acc_coeff' => ''
                ];
                $this->logger->warning(
                    self::fmt('No data not found for leader %s', $leader['lead_acc_no'])
                );
            }

            $stmt = $connection->prepare("
                SELECT
                    aa.Balance                      AS aggr_acc_balance,
                    FROM_UNIXTIME(aa.Registration)  AS aggr_acc_created_at
                FROM mt5_user AS aa
                WHERE aa.Login = :acc_no AND aa.frs_RecOperation <> 'D'
            ");

            $stmt->execute([
                'acc_no' => $leader['aggr_acc_no'],
            ]);

            $aggregatorData = $stmt->fetch(PDO::FETCH_ASSOC);
            if($aggregatorData === false) {
                $aggregatorData = [
                    'aggr_acc_balance' => '',
                    'aggr_acc_created_at' => '',
                ];
                $this->logger->warning(
                    self::fmt('No data not found for aggregator of leader account %s', $leader['lead_acc_no'])
                );
            }
        }
        catch (Throwable $exception) {
            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );
        }

        return array_merge($leader, $leaderData, $aggregatorData);
    }

    /**
     * @param array $leaders
     */
    private function sendMessageToTeamwox(array $leaders): void
    {
        $msgRows = array_reduce(
            $leaders,
            function (string $carry, array $leader) {
                return $carry . "
                    <tr>
                      <td>" . ($leader["lead_acc_no"] ?? '') . "</td>
                      <td>" . ($leader["lead_acc_group"] ?? '') . "</td>
                      <td>" . ($leader["lead_acc_coeff"] ?? '') . "</td>
                      <td>" . ($leader["lead_acc_id"] ?? '') . "</td>
                      <td>" . ($leader["aggr_acc_created_at"] ?? '') . "</td>
                      <td>" . ($leader["aggr_acc_no"] ?? '') . "</td>
                      <td>" . ($leader["aggr_acc_balance"] ?? '') . "</td>
                      <td>" . ($leader["foll_acc_opened_at"] ?? '') . "</td>
                      <td>" . ($leader["foll_acc_no"] ?? '') . "</td>
                    </tr>
                ";
            },
            ''
        );

        $msg = sprintf(
            "
                <table class='standart' border='0' cellpadding='5' cellspacing='0'>
                    <thead>
                        <tr>
                            <th style='text-align: center;'>Login</th>
                            <th style='text-align: center;'>Group</th>
                            <th style='text-align: center;'>Coefficient</th>
                            <th style='text-align: center;'>ID</th>
                            <th style='text-align: center;'>Aggregator Created</th>
                            <th style='text-align: center;'>Aggregator Login</th>
                            <th style='text-align: center;'>Aggregator Balance</th>
                            <th style='text-align: center;'>Follower Created</th>
                            <th style='text-align: center;'>Follower Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        %s
                    </tbody>
                </table>
            ",
            $msgRows
        );

        try {
            $this
                ->teamwoxService
                ->getTask(24408)
                ->addComment($msg)
                ->save();
        } catch (Throwable $exception) {
            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );
        }
    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}
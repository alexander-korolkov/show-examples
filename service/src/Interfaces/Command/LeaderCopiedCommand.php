<?php


namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Environment;
use Fxtm\CopyTrading\Application\Leader\EnableCopyingWorkflow;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\TwGateClient\TeamWoxService;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class LeaderCopiedCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceFactory
     */
    private $dataSource;

    /**
     * @var TeamWoxService
     */
    private $teamWoxService;

    /**
     * @var int
     */
    private $taskId;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setDataSource(DataSourceFactory $dataSourceFactory): void
    {
        $this->dataSource = $dataSourceFactory;
    }

    public function setTeamVoxService(TeamWoxService $service): void
    {
        $this->teamWoxService = $service;
    }

    public function setTaskID($taskID): void
    {
        $this->taskId = (int)$taskID;
        if($this->taskId < 1000) {
            throw new \InvalidArgumentException("Task ID can't be less 1000, but {$taskID} given");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:leader_copied')
            ->setDescription('Makes a notification into TeamWox when a follower performs a balance operation')
            ->setHelp('Makes a notification into TeamWox when a follower performs a balance operation');
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('LeaderCopiedCommand started.');
        if (Environment::isTestUname()) {
            $this->logger->info('LeaderCopiedCommand cant be ran in test env.');
            return 0;
        }

        $connectionCT = $this->dataSource->getCTConnection();

        $result = $connectionCT->query("
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
                w.type = '" . EnableCopyingWorkflow::TYPE . "'
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {

            $this->logger->info('LeaderCopiedCommand finished.');
            return 0;
        }

        $generateWorkflowRow = function (array $data) {
            return "
                <tr>
                  <td>{$data["lead_acc_no"]}</td>
                  <td>{$data["lead_acc_group"]}</td>
                  <td>{$data["lead_acc_coeff"]}</td>
                  <td>{$data["lead_acc_id"]}</td>
                  <td>{$data["aggr_acc_created_at"]}</td>
                  <td>{$data["aggr_acc_no"]}</td>
                  <td>{$data["aggr_acc_balance"]}</td>
                  <td>{$data["foll_acc_opened_at"]}</td>
                  <td>{$data["foll_acc_no"]}</td>
                </tr>
            ";
        };

        $rows = "";
        foreach ($result as $row) {
            $connectionFRS = $this->dataSource->getFrsConnection($row['lead_acc_server']);
            $leadAcc = $connectionFRS->query("
                SELECT
                    la.group      AS lead_acc_group,
                    la.id         AS lead_acc_id,
                    po.hedge_rate AS lead_acc_coeff
                FROM mt4_user_record AS la
                LEFT JOIN (
                    SELECT
                        ROUND(tr.gw_volume / tr.volume, 4) AS hedge_rate
                    FROM mt4_trade_record AS tr
                    WHERE tr.login = {$row["lead_acc_no"]} AND tr.gw_volume > 0.0 AND tr.frs_RecOperation <> 'D'
                    LIMIT 1
                ) po ON 1
                WHERE la.login = {$row["lead_acc_no"]} AND la.frs_RecOperation <> 'D'
            ")->fetch(PDO::FETCH_ASSOC);

            $aggAcc = $connectionFRS->query("
                SELECT
                    aa.Balance                      AS aggr_acc_balance,
                    FROM_UNIXTIME(aa.Registration)  AS aggr_acc_created_at
                FROM mt5_user AS aa
                WHERE aa.Login = {$row["aggr_acc_no"]} AND aa.frs_RecOperation <> 'D'
            ")->fetch(PDO::FETCH_ASSOC);

            $rows .= $generateWorkflowRow(array_merge($row, $leadAcc, $aggAcc));
        }

        $this->teamWoxService->getTask($this->taskId)->addComment(
            sprintf(
                "<table class='standart' border='0' cellpadding='5' cellspacing='0'>
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
                </table>",
                $rows
            )
        )->save();
        $this->logger->info('LeaderCopiedCommand finished.');
        return 0;
    }

}

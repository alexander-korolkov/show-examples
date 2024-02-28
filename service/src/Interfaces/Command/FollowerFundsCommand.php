<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Fxtm\TwGateClient\TeamWoxService;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow as CloseFollowerAccount;
use Fxtm\CopyTrading\Application\Follower\ProcessPayoutWorkflow as ProcessPayout;
use Fxtm\CopyTrading\Application\Follower\ProcessDepositWorkflow as ProcessFollowerDeposit;
use Fxtm\CopyTrading\Application\Follower\ProcessWithdrawalWorkflow as ProcessFollowerWithdrawal;



class FollowerFundsCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TeamWoxService
     */
    private $teamWoxService;

    /**
     * @var int
     */
    private $taskID;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    public function setTeamVoxService(TeamWoxService $service): void
    {
        $this->teamWoxService = $service;
    }

    public function setTaskID($taskID): void
    {
        $this->taskID = (int)$taskID;
        if($this->taskID < 1000) {
            throw new \InvalidArgumentException("Task ID can't be less 1000, but {$taskID} given");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:follower_funds')
            ->setDescription('Makes a notification into TeamWox when a follower performs a balance operation')
            ->setHelp('Makes a notification into TeamWox when a follower performs a balance operation');
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('FollowerFunds started.');
        $workflowTypes = implode("', '", [CloseFollowerAccount::TYPE, ProcessPayout::TYPE, ProcessFollowerDeposit::TYPE,
            ProcessFollowerWithdrawal::TYPE]);
        $workflows = $this->connection->query("
            SELECT *
            FROM workflows
            WHERE
            state = 2 AND
            finished_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND
            type IN ('{$workflowTypes}')
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($workflows)) {
            $this->logger->info('FollowerFunds finished - No workflows found.');
            return 0;
        }

        $generateWorkflowRow = function (array $workflow) {
            $tpl = "
                <tr>
                    <td>{$workflow["id"]}</td>
                    <td>{$workflow["parent_id"]}</td>
                    <td>{$workflow["type"]}</td>
                    <td>{$workflow["state"]}</td>
                    <td>{$workflow["tries"]}</td>
                    <td>{$workflow["created_at"]}</td>
                    <td>{$workflow["started_at"]}</td>
                    <td>{$workflow["finished_at"]}</td>
                    <td>
                        <table class='standart' border='0' cellpadding='5' cellspacing='0'>
                          <thead><tr>%s</tr></thead>
                          <tbody><tr>%s</tr></tbody>
                        </table>
                    </td>
                </tr>
            ";
            $cols = "";
            $rows = "";
            foreach (json_decode($workflow["context"], true) as $key => $value) {
                $cols .= "<th style='text-align: center;'>{$key}</th>";
                $rows .= "<td>{$value}</td>";
            }
            return sprintf($tpl, $cols, $rows);
        };

        $rows = "";
        foreach ($workflows as $workflow) {
            $rows .= $generateWorkflowRow($workflow);
        }
        $this->teamWoxService->getTask($this->taskID)->addComment(
            sprintf("<table class='standart' border='0' cellpadding='5' cellspacing='0'>
                          <thead>
                            <tr>
                              <th style='text-align: center;'>ID</th>
                              <th style='text-align: center;'>Parent ID</th>
                              <th style='text-align: center;'>Type</th>
                              <th style='text-align: center;'>State</th>
                              <th style='text-align: center;'>Tries</th>
                              <th style='text-align: center;'>Created At</th>
                              <th style='text-align: center;'>Started At</th>
                              <th style='text-align: center;'>Finished At</th>
                              <th style='text-align: center;'>Context</th>
                            </tr>
                          </thead>
                          <tbody>
                            %s
                          </tbody>
                        </table>",
                $rows)
        )->save();
        $this->logger->info('FollowerFunds finished.');
        return 0;
    }

}

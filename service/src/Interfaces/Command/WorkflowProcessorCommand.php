<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Exception;
use Throwable;

class WorkflowProcessorCommand extends Command
{

    use LockableTrait;

    private const COMMAND           = 'app:workflow_run';
    private const PROCESSED_LIMIT   = 10;

    /**
     * @var string
     */
    private $php = 'php';

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @var Connection
     */
    private $dbConn;

    /**
     * @var bool
     */
    private $running = true;


    /**
     * @param Connection $dbConn
     */
    public function setDBConnection(Connection $dbConn): void
    {
        $this->dbConn = $dbConn;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:workflow_processor_daemon')
            ->setDescription('Calls to the daemon of workflow processing')
            ->addOption('workers', 'w', InputOption::VALUE_OPTIONAL, "Maximum number of workers; Default = " . self::PROCESSED_LIMIT, self::PROCESSED_LIMIT)
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {

        $limit = intval($input->getOption('workers'));
        if($limit == 0) {
            $limit = self::PROCESSED_LIMIT;
        }

        if(!$this->lock()) {
            $output->writeln("Another process is running");
            $this->logger->error(
                self::fmt("Failed to set lock, another process is running")
            );
            return  -1;
        }

        try {
            $status = $this->_execute($limit);
        }
        catch (\Throwable $t) {

            $this->logger->critical(
                self::fmt("Exception occurred: %s\n%s", [$t->getMessage(), $t->getTraceAsString()])
            );

            $status = -1;
        }

        $this->release();

        return $status;
    }

    private function _execute(int $limit) : int
    {
        $this->logger->info(self::fmt("Processing server is started"));
        declare(ticks=1);
        pcntl_signal(SIGINT, function ($signal) {
            $this->logger->info(self::fmt("SIGINT: shutting down..."));
            $this->stop();
        });
        pcntl_signal(SIGTERM, function ($signal) {
            $this->logger->info(self::fmt("SIGTERM: shutting down..."));
            $this->stop();
        });
        $this->cwd = getcwd();
        if($this->cwd === false) {
            $this->logger->error(self::fmt("Failed to get CWD"));
            return -1;
        }
        $processes = [];
        while ($this->isRunning())
        {
            sleep(1);
            try {
                $isAllowedProcessingLeaderWorkflow = $this->isAllowedProcessingLeaderWorkflow();
                $isAllowedProcessingFollowerWorkflow = $this->isAllowedProcessingFollowerWorkflow();

                // skip iteration if both workflow processors are disabled
                if (!$isAllowedProcessingLeaderWorkflow && !$isAllowedProcessingFollowerWorkflow) {
                    continue;
                }

                foreach (
                    $this->getWorkflows($isAllowedProcessingLeaderWorkflow, $isAllowedProcessingFollowerWorkflow)
                    as $processId
                    => $workflows
                ) {
                    if (isset($processes[$processId])) {
                        continue;
                    }
                    // each process executes many workflows aggregated by account number
                    $processes[$processId] = $this->createProcess($workflows, $processId);
                    $this->wait($processes, $limit);
                }
            }
            catch (Exception $dbExceptions) {
                $this
                    ->logger
                    ->warning("Iteration skipped due to previous exception");
            }
            $this->clean($processes);
        }
        $this->logger->warning(self::fmt("Processing server is interrupted"));
        return 0;
    }

    /**
     * Returns true if processing is not stopped
     * @return bool
     */
    private function isRunning() : bool
    {
        return $this->running;
    }

    /**
     * Stops execution of processing
     */
    private function stop() : void
    {
        $this->running = false;
    }

    /**
     * Checks if processing of leader workflow is allowed
     *
     * @return bool
     * @throws Exception
     */
    private function isAllowedProcessingLeaderWorkflow(): bool
    {
        if (!$this->running) {
            return false;
        }

        return (bool) $this->getServiceSettingByName(SettingsRegistry::LEADER_WORKFLOW_PROCESSING_SETTING_NAME);
    }

    /**
     * Checks if processing of follower workflow is allowed
     *
     * @return bool
     * @throws Exception
     */
    private function isAllowedProcessingFollowerWorkflow(): bool
    {
        if (!$this->running) {
            return false;
        }

        return (bool) $this->getServiceSettingByName(SettingsRegistry::FOLLOWER_WORKFLOW_PROCESSING_SETTING_NAME);
    }

    /**
     * @param string $settingName
     * @return string
     * @throws Exception
     */
    private function getServiceSettingByName(string $settingName): string
    {
        $sql = 'SELECT value FROM service_settings WHERE setting = :setting_name';

        try {
            $stmt = $this->dbConn->prepare($sql);
            $result = $stmt->executeQuery([':setting_name' => $settingName]);

            return (string) $result->fetchOne();
        } catch (Throwable $exception) {
            $this->logger->critical(
                self::fmt("Exception occurred (getServiceSettingByName): %s\n%s", [$exception->getMessage(), $exception->getTraceAsString()])
            );
            throw new Exception('Query execution cause to exception', 0, $exception);
        }
    }

    /**
     * Fetches list of workflows to be processed
     *
     * https://tw.fxtm.com/servicedesk/view/65115
     *
     * This query does the following
     *
     * 1. finds all pending and due workflows
     * 2. groups them by accounts
     * 3. sorts them by IDs within those groups
     * 4. puts child ones in front of parent ones
     * 5. takes only the 1st one from each group
     *
     * UNION
     *
     * workflows that can be processed out of order
     *
     * @param bool $isAllowedProcessingLeaderWorkflow
     * @param bool $isAllowedProcessingFollowerWorkflow
     * @return array
     * @throws Exception
     */
    private function getWorkflows(bool $isAllowedProcessingLeaderWorkflow, bool $isAllowedProcessingFollowerWorkflow): array
    {
        $additionalConditions = '';
        if (!$isAllowedProcessingLeaderWorkflow) {
            $additionalConditions .= " AND w.type NOT LIKE 'leader.%'";
        }

        if (!$isAllowedProcessingFollowerWorkflow) {
            $additionalConditions .= " AND w.type NOT LIKE 'follower.%'";
        }

        try {
            $workflows = $this
                ->dbConn
                ->executeQuery("
                    SELECT
                        w.id, w.corr_id
                    FROM workflows AS w
                    WHERE 
                        w.state < 2 AND w.parent_id IS NULL AND w.scheduled_at <= NOW()
                        {$additionalConditions}
                    ORDER BY 
                        if (w.type IN ('leader.change_remun_fee', 'leader.update_account_name', 'leader.update_description', 'follower.open_account'), 1, 0), 
                        w.corr_id,
                        w.scheduled_at, 
                        w.id")
                ->fetchAllAssociative();

            $result = [];
            foreach ($workflows as $workflow) {
                $processId = '__' . $workflow['corr_id'];

                if (!isset($result[$processId])) {
                    $result[$processId] = [];
                }

                $result[$processId][] = (int) $workflow['id'];
            }

            return $result;
        } catch (Throwable $exception) {
            $this->logger->critical(
                self::fmt("Exception occurred (getWorkflows): %s\n%s", [$exception->getMessage(), $exception->getTraceAsString()])
            );
            throw new Exception("Query execution cause to exception", 0, $exception);
        }
    }

    /**
     * Creates and runs sub process
     * @param int[] $ids
     * @param string $processId
     * @return Process
     */
    private function createProcess(array $ids, string $processId) : Process
    {
        $command = array_merge([$this->php, "/home/site/ct-api/current/bin/console",  self::COMMAND], $ids);
        $process = new Process($command, $this->cwd);
        $process->setTimeout(null);
        $process->start();
        $processArgs = implode(', ', $ids);
        $this->logger->info(self::fmt("Started process {$processId} => [{$processArgs}] (PID: {$process->getPid()})"));
        return $process;
    }

    /**
     * Waits until number of running processes decreased below limit
     *
     * @param Process[] $processes
     * @param int $limit
     */
    private function wait(array &$processes, int $limit) : void
    {
        while (count($processes) > $limit) {
            $this->clean($processes);
            sleep(1);
        }
    }

    /**
     * Removes processes which has been finished
     *
     * @param array $processes
     */
    private function clean(array &$processes) : void
    {
        foreach (array_keys($processes) as $key) {
            /** @var Process $process */
            $process = $processes[$key];
            try {
                $process->checkTimeout();
            }
            catch (ProcessTimedOutException $toException) {
                $this->logger->warning(
                    self::fmt(
                        "Process {$key} (PID: {$process->getPid()}) ran out of time
                        OUT: {$process->getOutput()};
                        ERR: {$process->getErrorOutput()}"),
                    [ 'exception' => $toException ]
                );
                unset($processes[$key]);
            }
        }
        foreach (array_keys($processes) as $key) {
            /** @var Process $process */
            $process = $processes[$key];
            if (!$process->isRunning()) {
                $this->logger->info(
                    "Process {$key} (PID: {$process->getPid()}) finished.\nExit code: {$process->getExitCode()};\nOUT: {$process->getOutput()};\nERR: {$process->getErrorOutput()}"
                );
                unset($processes[$key]);
            }
        }
    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[DAEMON %s]: %s", self::class, vsprintf($msg, $params));
    }

}
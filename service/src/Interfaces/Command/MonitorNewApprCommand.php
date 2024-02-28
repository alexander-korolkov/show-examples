<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow;
use Fxtm\CopyTrading\Application\Follower\LockInSafeModeWorkflow;
use Fxtm\CopyTrading\Application\LeverageService;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Gateway\Account\ServerAccountTypes;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @deprecated
 *
 * seems this code can be removed
 *
 * Class MonitorNewApprCommand
 * @package Fxtm\CopyTrading\Interfaces\Command
 */
class MonitorNewApprCommand extends Command
{

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceFactory
     */
    private $factory;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * @var SettingsRegistry
     */
    private $settings;

    /**
     * @var LeverageService $leverageSvc
     */
    private $leverage;

    /** @var TradeAccountGateway $accGateway */
    private $accGateway;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    /**
     * @param DataSourceFactory $factory
     */
    public function setFactory(DataSourceFactory $factory) : void
    {
        $this->factory = $factory;
    }

    /**
     * @param WorkflowManager $workflowManager
     */
    public function setWorkflowManager(WorkflowManager $workflowManager) : void
    {
        $this->workflowManager = $workflowManager;
    }

    /**
     * @param SettingsRegistry $settings
     */
    public function setSettings(SettingsRegistry $settings) : void
    {
        $this->settings = $settings;
    }

    /**
     * @param LeverageService $leverage
     */
    public function setLeverage(LeverageService $leverage) : void
    {
        $this->leverage = $leverage;
    }

    /**
     * @param TradeAccountGateway $accGateway
     */
    public function setAccGateway(TradeAccountGateway $accGateway) : void
    {
        $this->accGateway = $accGateway;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:monitor_new_apprtest_results')
            ->setDescription('')
            ->addOption('broker', 'b', InputOption::VALUE_REQUIRED, "Broker name")
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $broker = $input->getOption("broker");

        if(!$this->lock($broker . '.' . $this->getName())) {
            $output->writeln("Another process is running");
            $this->logger->error(
                self::fmt("Failed to set lock, another process is running")
            );
            return  -1;
        }

        $status = 0;

        try {
            $this->logger->info("Started: %s", [$broker]);

            $this->processFollowers($this->getNonClosedFollowers($broker), $broker);

            $this->logger->info("Done: %s", [$broker]);
        }
        catch (Throwable $exception) {

            $status = -1;

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        $this->release();

        return $status;
    }

    /**
     * @param string $broker
     * @return array
     */
    private function getNonClosedFollowers(string $broker) : array
    {
        try {
            $logins = $this
                ->factory
                ->getMyConnection($broker)
                ->executeQuery("
                    SELECT a.login
                    FROM account a
                    JOIN client c ON c.id = a.client_id AND c.company_id = 1 AND c.appropriateness_ts > '{$this->settings->get("appropriateness_test.last_check")}'
                    WHERE a.account_type_id IN (27, 34) AND a.status_id = 2
                ")
                ->fetchAll(PDO::FETCH_COLUMN);

            $counter = 0;
            $followers = [];
            foreach (array_chunk($logins, 100) as $chunk) {
                $accNos = implode(",", $chunk);
                $accounts = $this
                    ->factory
                    ->getCTConnection()
                    ->executeQuery("
                    SELECT owner_id, acc_no, lead_acc_no, copy_coef
                    FROM follower_accounts
                    WHERE acc_no IN ({$accNos}) AND status < 2
                    ")
                    ->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($accounts as $acc) {
                    $followers[$acc["owner_id"]][] = $acc;
                    $counter ++;
                }
            }

            $this->logger->info(self::fmt("Found %d followers for broker %s", [$counter, $broker]));

            return $followers;
        }
        catch (Throwable $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }
        return [];
    }

    /**
     * @param array $followers
     * @param string $broker
     */
    private function processFollowers(array $followers, string $broker) {

        foreach ($followers as $ownerId => $accs) {

            foreach ($accs as $acc) {

                try {
                    $tradeAcc = $this
                        ->accGateway
                        ->fetchAccountByNumber(new AccountNumber($acc["acc_no"]), $broker);

                    $lvr = $this->leverage->getMaxAllowedLeverageForClientAndAccountType(
                        $tradeAcc->ownerId(),
                        $broker,
                        $tradeAcc->accountTypeId()
                    );

                    if ($tradeAcc->leverage() == $lvr) {
                        continue;
                    }

                    $this
                        ->accGateway
                        ->changeAccountLeverage(new AccountNumber($acc["acc_no"]), $broker, $lvr);

                    $ratio = $this->leverage->getLeverageRatio(
                        new AccountNumber($acc["lead_acc_no"]), $tradeAcc->ownerId(),
                        $broker
                    );

                    if ($ratio <= 1) {
                        $this->unlockCopying($acc["acc_no"]);
                        $this->unlockCopyCoef($acc["acc_no"]);
                        continue;
                    }

                    if ($ratio > 1 && $ratio <= 2) {
                        $this->unlockCopying($acc["acc_no"]);

                        $lockInSafeMode = $this->workflowManager->newWorkflow(
                            LockInSafeModeWorkflow::TYPE,
                            new ContextData([
                                "accNo" => $acc["acc_no"],
                                ContextData::KEY_BROKER => $broker,
                            ])
                        );
                        $this->workflowManager->enqueueWorkflow($lockInSafeMode);
                        continue;
                    }

                    if ($ratio > 2) {

                        $closeAccount = $this->workflowManager->newWorkflow(
                            CloseAccountWorkflow::TYPE,
                            new ContextData([
                                "accNo" => $acc["acc_no"],
                                "reason" => CloseAccountWorkflow::REASON_INCOMPATIBLE_LEVERAGE,
                                ContextData::KEY_BROKER => $broker,
                            ])
                        );

                        $this->workflowManager->enqueueWorkflow($closeAccount);

                        continue;
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
            }
        }

        $this->settings->set("appropriateness_test.last_check", DateTime::NOW());
    }

    /**
     * @param string $acc
     * @return int
     */
    private function unlockCopying(string $acc) : int
    {
        try {

            return $this
                ->factory
                ->getCTConnection()
                ->executeStatement("
                    UPDATE follower_accounts SET lock_copying = 0 WHERE acc_no = {$acc} AND lock_copying = 1
                ");
        }
        catch (Throwable $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        return 0;
    }

    /**
     * @param string $acc
     * @return int
     */
    private function unlockCopyCoef(string $acc) : int
    {
        try {

            return $this
                ->factory
                ->getCTConnection()
                ->executeStatement("
                    UPDATE follower_accounts SET lock_copy_coef = 0 WHERE acc_no = {$acc} AND lock_copy_coef = 1
                ");
        }
        catch (Throwable $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        return 0;
    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}
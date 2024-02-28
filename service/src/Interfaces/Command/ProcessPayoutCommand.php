<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\Follower\ProcessPayoutWorkflow;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\BlockedAccount;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessPayoutCommand extends Command
{

    use LockableTrait;

    /**
     * @var Connection
     */
    private $dbConnection;

    /**
     * @var FollowerAccountRepository
     */
    private $accountsRepository;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param Connection $dbConnection
     */
    public function setDbConnection(Connection $dbConnection) : void
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * @param FollowerAccountRepository $repository
     */
    public function setAccountsRepository(FollowerAccountRepository $repository) : void
    {
        $this->accountsRepository = $repository;
    }

    /**
     * @param WorkflowManager $manager
     */
    public function setWorkflowManager(WorkflowManager $manager) : void
    {
        $this->workflowManager = $manager;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:process_payouts')
            ->setDescription('Creates payout workflows')
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if(!$this->lock()) {
            $output->writeln("Another process is running");
            $this->logger->error(
                self::fmt("Failed to set lock, another process is running")
            );
            return  -1;
        }

        $status = 0;

        try {

            $states = [WorkflowState::UNTRIED, WorkflowState::PROCEEDING, WorkflowState::FAILED];
            $statesRow = implode(',', $states);
            $stmt = $this
                ->dbConnection
                ->prepare("
                  SELECT EXISTS(SELECT * FROM workflows WHERE `type` = ? AND corr_id = ? AND `state` IN ({$statesRow}))
                ");


            foreach ($this->accountsRepository->findWithDuePayoutInterval() as $account) {

                $stmt->execute([ProcessPayoutWorkflow::TYPE, $account->number()->value()]);
                if ($stmt->fetchColumn()) {

                    $this->logger->warning(
                        self::fmt("Already in process")
                    );

                    continue;
                }

                try {
                    $accOpenedAt = $account->openedAt();
                    $scheduledAt = DateTime::NOW()->setTime($accOpenedAt->format("H"), $accOpenedAt->format("i"));
                    if (DateTime::NOW() >= $scheduledAt) {
                        $workflow = $this->workflowManager->newWorkflow(
                            ProcessPayoutWorkflow::TYPE,
                            new ContextData([
                                "accNo" => $account->number()->value(),
                                "accCurr" => $account->currency()->code(),
                                ContextData::KEY_BROKER => $account->broker(),
                            ])
                        );

                        $workflow->scheduleAt($scheduledAt);
                        $this->workflowManager->enqueueWorkflow($workflow);

                        $this->logger->info(
                            self::fmt("Account %s have been processed", [$account->number()])
                        );

                    }
                }
                catch (BlockedAccount $e) {
                    $this->logger->warning(
                        self::fmt("Account %s is blocked", [$account->number()])
                    );
                }
                catch (\Exception $e) {

                    $this->logger->error(
                        self::fmt(
                            "Exception occurred: %s\n%s",
                            [$e->getMessage(), $e->getTraceAsString()]
                        )
                    );

                }

            }
        }
        catch (\Throwable $exception) {

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

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}
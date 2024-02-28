<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Follower\PauseCopyingWorkflow;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use \Exception;

class PauseFollowersWOApprCommand extends Command
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
     * @var TradeOrderGatewayFacade
     */
    private $orderGateway;

    /**
     * @var WorkflowManager
     */
    private $workflowMngr;

    /**
     * @var NotificationGateway
     */
    private $notifGateway;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param DataSourceFactory $factory
     */
    public function setFactory(DataSourceFactory $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * @param TradeOrderGatewayFacade $orderGateway
     */
    public function setOrderGateway(TradeOrderGatewayFacade $orderGateway): void
    {
        $this->orderGateway = $orderGateway;
    }

    /**
     * @param WorkflowManager $workflowMngr
     */
    public function setWorkflowMngr(WorkflowManager $workflowMngr): void
    {
        $this->workflowMngr = $workflowMngr;
    }

    /**
     * @param NotificationGateway $notifGateway
     */
    public function setNotifGateway(NotificationGateway $notifGateway): void
    {
        $this->notifGateway = $notifGateway;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:pause_followers_wo_apprtest')
            ->setDescription('Puts on pause followers accounts without passed apprtest')
            ->addOption('broker', 'b', InputOption::VALUE_REQUIRED, "Broker name")
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

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

            $broker = $input->getOption('broker');

            $this->logger->info("Started: %s", [$broker]);

            $this
                ->pauseFollowers(
                    $this->getNotClosedFollowersAccounts($broker),
                    $broker
                );

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

    private function getNotClosedFollowersAccounts(string $broker) : array
    {

        try {

            $connection = $this
                ->factory
                ->getMyConnection($broker);

            $logins = $connection
                ->executeQuery("
                    SELECT a.login
                    FROM account a
                    JOIN client c ON c.id = a.client_id AND c.company_id = 1 AND c.appropriateness_score IS NULL
                    WHERE a.account_type_id IN (27, 34) AND a.status_id = 2
                ")
                ->fetchAll(PDO::FETCH_COLUMN);

            $followers = [];
            foreach (array_chunk($logins, 100) as $chunk) {
                $accNos = implode(",", $chunk);
                $accounts = $this
                    ->factory
                    ->getCTConnection()
                    ->executeQuery("
                        SELECT fa.acc_no, fa.owner_id, la.server
                        FROM follower_accounts fa
                        JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no
                        WHERE fa.acc_no IN ({$accNos}) AND fa.status = 1 AND fa.is_copying = 1
                    ")
                    ->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($accounts as $account) {
                    $followers[$account["owner_id"]][$account["acc_no"]] = $account["server"];
                }
            }

            return $followers;
        }
        catch (Exception $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        return [];
    }

    private function pauseFollowers(array $followers, string $broker) : void
    {
        foreach ($followers as $ownerId => $accounts)
        {
            $accountsTobePaused = [];
            foreach ($accounts as $account => $server)
            {

                if ($this->orderGateway->getForServer($server)->hasOpenPositions(new AccountNumber($account))) {
                    continue;
                }

                $workflow = $this
                    ->workflowMngr
                    ->newWorkflow(
                        PauseCopyingWorkflow::TYPE,
                        new ContextData([
                            "accNo"  => $account,
                            "reason" => PauseCopyingWorkflow::REASON_CLIENT_APPRTEST,
                            ContextData::KEY_BROKER => $broker,
                        ])
                    );

                $this
                    ->workflowMngr
                    ->enqueueWorkflow($workflow);

                $accountsTobePaused[] = $account;
            }

            if (!empty($accountsTobePaused)) {
                $this
                    ->notifGateway
                    ->notifyClient(
                        new ClientId($ownerId),
                        $broker,
                        NotificationGateway::FOLLOWER_PAUSED_NO_APPRTEST,
                        ["accounts" => $accountsTobePaused]
                    );
            }

        }

    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}
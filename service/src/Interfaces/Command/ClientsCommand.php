<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Follower\PauseCopyingWorkflow;
use Fxtm\CopyTrading\Domain\Model\Client\Client;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClientsCommand extends Command
{

    use LockableTrait;

    /**
     * @var Connection
     */
    private $dbConnection;

    /**
     * @var WorkflowManager $workflowManager
     */
    private $workflowManager;

    /**
     * @var ClientGateway $clientGateway
     */
    private $clientGateway;

    /**
     * @var FollowerAccountRepository $followerRepository
     */
    private $followerRepository;

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
     * @param WorkflowManager $workflowManager
     */
    public function setWorkflowManager(WorkflowManager $workflowManager) : void
    {
        $this->workflowManager = $workflowManager;
    }

    /**
     * @param ClientGateway $clientGateway
     */
    public function setClientGateway(ClientGateway $clientGateway) : void
    {
        $this->clientGateway = $clientGateway;
    }

    /**
     * @param FollowerAccountRepository $followerRepository
     */
    public function setFollowerRepository(FollowerAccountRepository $followerRepository) : void
    {
        $this->followerRepository = $followerRepository;
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
        $this->setName('app:clients')
            ->setDescription('')
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

            $this->logger->info(self::fmt("Started"));

            $this->pauseCoping($this->getInvestors());

            $this->logger->info(self::fmt("Done"));
        }
        catch (\Throwable $throwable) {

            $status = -1;

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$throwable->getMessage(), $throwable->getTraceAsString()]
                )
            );

        }

        $this->release();

        return $status;
    }

    /**
     * Fetches list of investors accounts grouped by broker id
     * @return array
     */
    private function getInvestors() : array
    {
        $investors = [];

        try {

            $counter = 0;
            foreach ($this->dbConnection->query("SELECT owner_id, acc_no, broker FROM follower_accounts WHERE is_copying = 1 ORDER BY owner_id", PDO::FETCH_ASSOC) as $row) {
                $investors[$row['broker']][$row["owner_id"]][] = $row["acc_no"];
                $counter ++;
            }

            $this->logger->info(self::fmt("Found %d accounts", [$counter]));

        }
        catch (DBALException $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        return $investors;
    }

    /**
     * @param array $investors list of investors accounts grouped by broker id
     */
    private function pauseCoping(array $investors) : void
    {

        try {

            $stmt = $this
                ->dbConnection
                ->prepare("
                    SELECT COUNT(1) FROM workflows WHERE `type` = ? AND corr_id = ? AND `state` IN (0, 1, 3)
                ");

            foreach ($investors as $broker => $brokerInvestors) {

                $this->logger->info(
                    self::fmt(
                        "Found %d investors of broker %s. Checking statuses...",
                        [sizeof($brokerInvestors), $broker]
                    )
                );

                foreach ($brokerInvestors as $clientId => $follAccs) {

                    /** @var Client $client */
                    $client = $this->clientGateway->fetchClientByClientId(new ClientId($clientId), $broker);
                    if ($client->getStatusId() != 14) {
                        continue; // if not frozen
                    }

                    $this->logger->info(
                        self::fmt("Client %d has been frozen. Pausing follower accounts...", [$clientId])
                    );

                    foreach ($follAccs as $accNo) {

                        if (!$this->followerRepository->getLightAccount(new AccountNumber($accNo))->isCopying()) {
                            continue;
                        }

                        $stmt->execute([PauseCopyingWorkflow::TYPE, $accNo]);
                        if ($stmt->fetchColumn()) {
                            $this->logger->info(self::fmt("Already in process."));
                            continue;
                        }

                        $workflow = $this
                            ->workflowManager
                            ->newWorkflow(
                                PauseCopyingWorkflow::TYPE,
                                new ContextData(
                                    [
                                        "accNo" => $accNo,
                                        "reason" => PauseCopyingWorkflow::REASON_CLIENT_FROZEN,
                                        ContextData::KEY_BROKER => $broker,
                                    ]
                                )
                            );

                        $this
                            ->workflowManager
                            ->enqueueWorkflow($workflow);


                    }

                }
            }
        }
        catch (DBALException $exception) {

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
<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Application\Transfer;
use Fxtm\CopyTrading\Domain\Model\Account\TradeAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Fxtm\CopyTrading\Application\Follower\ProcessDepositWorkflow as FollowerDeposit;
use Fxtm\CopyTrading\Application\Leader\ProcessDepositWorkflow as LeaderDeposit;
use Fxtm\CopyTrading\Application\Leader\ProcessWithdrawalWorkflow as LeaderWithdrawal;

class TransfersCommand extends Command
{

    use LockableTrait;

    /**
     * @var TransactionGateway
     */
    private $transfersGateway;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var WorkflowRepository
     */
    private $workflowRepo;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * @var LeaderAccountRepository
     */
    private $leadersRepository;

    /**
     * @var FollowerAccountRepository
     */
    private $followersRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    private $restrictedCountriesList = ['RU', 'IR'];

    /**
     * @param TransactionGateway $transfersGateway
     */
    public function setTransfersGateway(TransactionGateway $transfersGateway) : void
    {
        $this->transfersGateway = $transfersGateway;
    }

    /**
     * @param TradeAccountGateway $tradeAccountGateway
     */
    public function setTradeAccountGateway(TradeAccountGateway $tradeAccountGateway) : void
    {
        $this->tradeAccountGateway = $tradeAccountGateway;
    }

    /**
     * @param WorkflowRepository $workflowRepo
     */
    public function setWorkflowRepo(WorkflowRepository $workflowRepo) : void
    {
        $this->workflowRepo = $workflowRepo;
    }

    /**
     * @param WorkflowManager $workflowManager
     */
    public function setWorkflowManager(WorkflowManager $workflowManager) : void
    {
        $this->workflowManager = $workflowManager;
    }

    /**
     * @param LeaderAccountRepository $leadersRepository
     */
    public function setLeadersRepository(LeaderAccountRepository $leadersRepository) : void
    {
        $this->leadersRepository = $leadersRepository;
    }

    /**
     * @param FollowerAccountRepository $followersRepository
     */
    public function setFollowersRepository(FollowerAccountRepository $followersRepository) : void
    {
        $this->followersRepository = $followersRepository;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    /**
     * @param ClientGateway $clientGateway
     */
    public function setClientGateway(ClientGateway $clientGateway): void
    {
        $this->clientGateway = $clientGateway;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:transfers')
            ->setDescription('Creates workflow for each transfer for copytrading')
            ->addOption('broker', 'b', InputOption::VALUE_REQUIRED, "Broker name")
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
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

        try {
            $transfers = $this->transfersGateway->getTransactionsForCopyTrading($broker);
        }
        catch(\Throwable $e) {
            $this->logger->error(
                "{$broker}: Couldn't process the transfers: {$e->__toString()}",
                [ 'exception' => $e, 'broker' => $broker ]

            );
            return -1;
        }

        $this->logger->info("{$broker}: Load " . count($transfers) . " transfers.");

        $status = 0;

        try {
            /** @var Transfer $transfer */
            foreach ($transfers as $transfer)
            {
                try {
                    $account = $this->getAccountByTransfer($transfer, $broker);
                    if (!$account) {
                        continue;
                    }

                    if ($account->number()->value() < 10000) {
                        //backoffice test accounts, execute transfer without workflow
                        $this->executeTransfer($transfer, $broker);
                        continue;
                    }

                    if($this->checkRestrictions($transfer, $account, $broker)) {
                        if ($account->isLeaderAccount()) {
                            $this->executeTransfer($transfer, $broker);
                        } else {
                            // change CURRENT copy-trading executor to BO
                            $this->transfersGateway->changeExecutorToBackOffice(
                                $transfer->getId(),
                                $broker,
                                'Transfer are not processable and should be declined.'
                            );
                        }
                        continue;
                    }

                    $this->createWorkflow($transfer, $account, $broker);
                }
                catch (\Exception $e) {

                    $this->logger->error(
                        "{$broker}: Couldn't process the transfers: {$e->__toString()}",
                        ['exception' => $e, 'broker' => $broker]

                    );

                }

            }
        }
        catch (\Throwable $exception) {

            $status = -1;

            $this->logger->critical(
                self::fmt(
                    "{$broker}: Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        $this->release();

        return $status;
    }

    /**
     * Creates and enquires workflow related to transfer processing
     *
     * @param Transfer $transfer
     * @param TradeAccount $account
     * @param string $broker
     * @throws \Fxtm\CopyTrading\Interfaces\Gateway\Transaction\TransactionGatewayException
     */
    public function createWorkflow(Transfer $transfer, TradeAccount $account, string $broker) : void
    {
        $workflowType = null;
        $amount = 0;
        $currency = null;
        switch ($transfer->getStatus()) {
            case Transfer::STATUS_NEW: // withdrawal
                $amount = $transfer->getFromAmount();
                $currency = $transfer->getFromCurrency();
                if ($account->isLeaderAccount()) {
                    $workflowType = LeaderWithdrawal::TYPE;
                } else {
                    $message = sprintf("%s Wrong Account Type %s", $transfer->getId(), get_class($account));
                    $this->logger->warning("{$broker} Transfers: {$message}");
                    return;
                }
                break;
            case Transfer::STATUS_TAKE: // deposit
                $amount = $transfer->getToAmount();
                $currency = $transfer->getToCurrency();
                if ($account->isLeaderAccount()) {
                    $workflowType = LeaderDeposit::TYPE;
                } else if ($account->isFollowerAccount()) {
                    $workflowType = FollowerDeposit::TYPE;
                } else {
                    $message = sprintf("%s Wrong Account Type %s", $transfer->getId(), get_class($account));
                    $this->logger->warning("{$broker} Transfers: {$message}");
                    return;
                }
                break;
            default:
                $message = sprintf("%s Wrong Transfer Status %d", $transfer->getId(), $transfer->getStatus());
                $this->logger->warning("{$broker} Transfers: {$message}");
                return;
        }

        $workflows = array_filter(
            $this->workflowRepo->findByCorrelationIdAndType($account->number()->value(), $workflowType),
            function (AbstractWorkflow $workflow) use ($transfer)
            {
                return $workflow
                    ->getContext()
                    ->getIfHas("transId") == $transfer->getId()
                ;
            }
        );

        if (!empty($workflows)) {
            $message = sprintf("%s Already in process",$transfer->getId());
            $this->logger->warning("{$broker} Transfer: {$message}");
            return;
        }

        if ($amount <= 0) {
            // change CURRENT copy-trading executor to BO
            $this->transfersGateway->changeExecutorToBackOffice($transfer->getId(), $broker, 'Transfer amount <= 0');
            $message = sprintf("%s Invalid amount (%.2f)", $transfer->getId(), $amount);
            $this->logger->warning("{$broker} Transfer: {$message}");
            return;
        }

        $ctx = new ContextData([
            "transId"  => $transfer->getId(),
            "accNo"    => $account->number()->value(),
            "amount"   => $amount,
            "accCurr"  => $currency,
            "clientId" => $account->ownerId()->value(),
            "internal" => $transfer->getTransferTypeId() == 3,
            ContextData::KEY_BROKER   => $broker,
        ]);

        $workflow = $this->workflowManager->newWorkflow($workflowType, $ctx);
        if ($this->workflowManager->enqueueWorkflow($workflow)) {
            $this->logger->info("{$broker} Transfer: {$transfer->getId()} - workflow {$workflow->id()} was created.");
        } else {
            $message = sprintf("%s workflow creation failed.", $transfer->getId());
            $this->logger->warning("{$broker} Transfer: {$message}");
        }
    }

    /**
     * @param Transfer $transfer
     * @param TradeAccount $account
     * @param string $broker
     * @return bool
     */
    public function checkRestrictions(Transfer $transfer, TradeAccount $account, string $broker) : bool
    {
        $client = $this
            ->clientGateway
            ->fetchClientByClientId($account->ownerId(), $broker)
        ;

        if(!$client) {
            $message = sprintf("%s Client not found %d", $transfer->getId(), $account->ownerId()->value());
            $this->logger->warning("{$broker} Transfers: {$message}");
            return false;
        }

        if($client->getCompany()->isEu()) {
            $message = sprintf(
                "%s Client from EU %d (%s)",
                $transfer->getId(),
                $account->ownerId()->value(),
                $client->getParam('country_code')
            );
            $this->logger->info("{$broker} Transfers: {$message}");
            return true;
        }

        if(
            in_array($client->getParam('country_code'), $this->restrictedCountriesList) &&
            !$client->getCompany()->isAby()
        ) {
            $message = sprintf(
                "%s Client is in restricted list %d (%s)",
                $transfer->getId(),
                $account->ownerId()->value(),
                $client->getParam('country_code')
            );
            $this->logger->info("{$broker} Transfers: {$message}");
            return true;
        }

        $message = sprintf(
            "%s Client is NOT in restricted list %d (%s)",
            $transfer->getId(),
            $account->ownerId()->value(),
            $client->getParam('country_code')
        );
        $this->logger->info("{$broker} Transfers: {$message}");

        return false;
    }

    /**
     * @param Transfer $transfer
     * @param string $broker
     * @return TradeAccount|null
     */
    private function getAccountByTransfer(Transfer $transfer, string $broker)
    {
        $account = null;
        switch ($transfer->getStatus()) {
            case Transfer::STATUS_NEW: // withdrawal
                $account = $this->tradeAccountGateway->fetchAccountByNumber($transfer->getFromAccountNumber(), $broker);
                break;
            case Transfer::STATUS_TAKE: // deposit
                $account = $this->tradeAccountGateway->fetchAccountByNumber($transfer->getToAccountNumber(), $broker);
                break;
            default:
                $message = sprintf("%s Wrong Transfer Status %d", $transfer->getId(), $transfer->getStatus());
                $this->logger->warning("{$broker} Transfers: {$message}");
                return null;
        }

        return $account;
    }

    /**
     * Executes transfer w/o creation of workflow in order to avoid conversion and creation copy-trading account
     *
     * @param Transfer $transfer
     * @param string $broker
     * @throws \Fxtm\CopyTrading\Interfaces\Gateway\Transaction\TransactionGatewayException
     */
    public function executeTransfer(Transfer $transfer, string $broker) : void
    {
        $status = null;
        switch ($transfer->getStatus()) {
            case Transfer::STATUS_NEW: // withdrawal
                $status = $this
                    ->transfersGateway
                    ->executeTransaction(
                        $transfer->getId(),
                        $broker,
                        TransactionGateway::TK_WITHDRAWAL,
                        $transfer->getFromAccountNumber()
                    );
                break;
            case Transfer::STATUS_TAKE: // deposit
                $status = $this
                    ->transfersGateway
                    ->executeTransaction(
                        $transfer->getId(),
                        $broker,
                        TransactionGateway::TK_DEPOSIT,
                        $transfer->getToAccountNumber()
                    );
                break;
            default:
                $message = sprintf("%s Wrong Transfer Status %d", $transfer->getId(), $transfer->getStatus());
                $this->logger->warning("{$broker} Transfers: {$message}");
                return;
        }

        if($status->getStatus() != TransactionGatewayExecuteResult::STATUS_OK) {
            $message = sprintf("%s Failed to execute transfer directly %d", $transfer->getId(), $status->getStatus());
            $this->logger->error("{$broker} Transfers: {$message}");
        }
    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }
}

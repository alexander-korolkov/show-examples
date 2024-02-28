<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\Transfer;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Interfaces\Gateway\Transaction\TransactionGatewayException;
use Fxtm\CopyTrading\Interfaces\Gateway\Transaction\TransferProxy;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckStuckTransactionsCommand extends Command
{

    use LockableTrait;

    private const COMMAND = 'app:check_stuck_transactions';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransactionGateway
     */
    private $gateway;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountsRepository;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    public function setTransactionGateway(TransactionGateway $gateway) : void
    {
        $this->gateway = $gateway;
    }

    public function setLeaderAccountRepository(LeaderAccountRepository $repository) : void
    {
        $this->leaderAccountRepository = $repository;
    }

    public function setFollowerAccountRepository(FollowerAccountRepository $repository) : void
    {
        $this->followerAccountsRepository = $repository;
    }

    public function setTradeAccountGateway(TradeAccountGateway $gateway) : void
    {
        $this->tradeAccountGateway = $gateway;
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
        $this->setName(self::COMMAND)
            ->setDescription('Checks stuck transaction, and declines if dst account is closed')
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if(!$this->lock($this->getName())) {
            $output->writeln("Another process is running");
            $this->logger->error(
                self::fmt("Failed to set lock, another process is running")
            );
            return  -1;
        }


        $this->logger->info(
            self::fmt('Started transactions check')
        );

        foreach(Broker::list() as $broker) {

            try {

                $transfers = $this->gateway->getTransactionsForCopyTrading($broker);

                $this->logger->info(
                    self::fmt('Found %s transfers for broker %s', [count($transfers), $broker])
                );

                /** @var TransferProxy $transfer */
                foreach($transfers as $transfer) {

                    if($this->isDeposit($transfer)) {

                        if($this->isToLeaderAccount($transfer, $broker)) {

                            $leader = $this->leaderAccountRepository->find($transfer->getToAccountNumber());

                            if($leader == null) {

                                $this->logger->warning(
                                    self::fmt('Leader account %s (%s) was not found in copy-trading DB; Skip transfer %s;', [
                                        $transfer->getToAccountNumber()->value(),
                                        $broker,
                                        $transfer->getId()
                                    ])
                                );

                                continue;
                            }

                            if ($leader->number()->value() < 10000) {

                                $this->logger->warning(
                                    self::fmt('Leader account %s (%s) is a backoffice test account; Skip transfer %s;', [
                                        $leader->number()->value(),
                                        $broker,
                                        $transfer->getId()
                                    ])
                                );

                                continue;
                            }

                            $client = $this->clientGateway->fetchClientByClientId($leader->ownerId(), $broker);

                            if ($client === null || $client->getCompany()->isEu()) {

                                $this->logger->warning(
                                    self::fmt('Leader account %s (%s) is from EU; Skip transfer %s;', [
                                        $leader->number()->value(),
                                        $broker,
                                        $transfer->getId()
                                    ])
                                );

                                continue;
                            }

                            if($leader->isClosed()) {

                                $this->logger->warning(
                                    self::fmt('Leader account %s (%s) is closed; Send transfer %s to the Backoffice', [
                                        $transfer->getToAccountNumber()->value(),
                                        $broker,
                                        $transfer->getId()
                                    ])
                                );

                                $this->sendToBackoffice($transfer, $broker);
                            }

                            continue;
                        }

                        if($this->isToFollowerAccount($transfer, $broker)) {

                            $follower = $this->followerAccountsRepository->findOrFail($transfer->getToAccountNumber());

                            if($follower == null) {

                                $this->logger->warning(
                                    self::fmt('Follower account %s (%s) was not found in copy-trading DB; Skip transfer %s', [
                                        $transfer->getToAccountNumber()->value(),
                                        $broker,
                                        $transfer->getId()
                                    ])
                                );

                                continue;
                            }

                            if($follower->isClosed()) {

                                $this->logger->warning(
                                    self::fmt('Follower account %s (%s) is closed; Send transfer %s to the Backoffice', [
                                        $transfer->getToAccountNumber()->value(),
                                        $broker,
                                        $transfer->getId()
                                    ])
                                );

                                $this->sendToBackoffice($transfer, $broker);

                            }

                            continue;
                        }
                    }

                }

            }
            catch (TransactionGatewayException $e) {
                $this->logger->warning(
                    self::fmt("Failed to get list of transfers for broker {$broker}"),
                    ['exception' => $e]
                );
                continue;
            }
        }

        $this->release();

        $this->logger->info(
            self::fmt('Transactions check done')
        );

        return 0;
    }

    private function isDeposit(TransferProxy $transfer) : bool
    {
        return $transfer->getStatus() == Transfer::STATUS_TAKE;
    }

    private function isToFollowerAccount(TransferProxy $transfer, string $broker) : bool
    {
        try {
            return $this
                ->tradeAccountGateway
                ->fetchAccountByNumber($transfer->getToAccountNumber(), $broker)
                ->isFollowerAccount();
        }
        catch (\Exception $exception) {
            $this->logger->warning(
                self::fmt("Failed to fetch account info"),
                ['exception' => $exception]
            );
            return false;
        }
    }

    private function isToLeaderAccount(TransferProxy $transfer, string $broker) : bool
    {
        try {
            return $this
                ->tradeAccountGateway
                ->fetchAccountByNumber($transfer->getToAccountNumber(), $broker)
                ->isLeaderAccount();
        }
        catch (\Exception $exception) {
            $this->logger->warning(
                self::fmt("Failed to fetch account info"),
                ['exception' => $exception]
            );
            return false;
        }
    }

    private function sendToBackoffice(TransferProxy $transfer, string $broker) : void
    {
        try {
            $this->gateway->changeExecutorToBackOffice(
                $transfer->getId(),
                $broker,
                sprintf('Account %s is closed', $transfer->getToAccountNumber()->value())
            );
        }
        catch (\Exception $exception) {
            $this->logger->warning(
                self::fmt("Failed to send transfer back to backoffice"),
                ['exception' => $exception]
            );
        }
    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}
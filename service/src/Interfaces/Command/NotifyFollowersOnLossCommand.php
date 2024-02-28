<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Statement;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\Client;
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

/**
 * Class NotifyFollowersOnLossCommand
 * @package Fxtm\CopyTrading\Interfaces\Command
 * @deprecated
 */
class NotifyFollowersOnLossCommand extends Command
{

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EquityService $equitySvc
     */
    private $equitySvc;

    /**
     * @var NotificationGateway $notifGateway
     */
    private $notifGateway;

    /**
     * @var ClientGateway $clientGateway
     */
    private $clientGateway;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param EquityService $equitySvc
     */
    public function setEquitySvc(EquityService $equitySvc): void
    {
        $this->equitySvc = $equitySvc;
    }

    /**
     * @param NotificationGateway $notifGateway
     */
    public function setNotifGateway(NotificationGateway $notifGateway): void
    {
        $this->notifGateway = $notifGateway;
    }

    /**
     * @param ClientGateway $clientGateway
     */
    public function setClientGateway(ClientGateway $clientGateway): void
    {
        $this->clientGateway = $clientGateway;
    }

    /**
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:notify_followers_on_loss')
            ->setDescription('Sends notification about losses to followers')
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

            $this
                ->sendNotifications($this->getAccounts());

            $this->logger->info(self::fmt("Done"));
        }
        catch (Throwable $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

            $status = -1;

        }

        $this->release();

        return $status;
    }

    private function getAccounts() : array
    {
        try {
            return $this
                ->connection
                ->query("SELECT acc_no, broker, acc_curr, owner_id, settled_at payout_interval_start FROM follower_accounts WHERE status = 1")
                ->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        }
        catch (DBALException $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        return [];
    }

    private function sendNotifications(array $accounts) : void
    {

        $step = 10;

        try {


            /* @var $select Statement */
            $select = $this
                ->connection
                ->prepare("SELECT loss_level FROM follower_loss_notifications WHERE acc_no = ?");

            /* @var $insert Statement */
            $insert = $this
                ->connection
                ->prepare("REPLACE INTO follower_loss_notifications VALUES (?, ?)");

        }
        catch (DBALException $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

            return;
        }

        foreach ($accounts as $accNo => $data) {

            try {

                $clientId = new ClientId($data["owner_id"]);

                /** @var Client $client */
                $client = $this->clientGateway->fetchClientByClientId($clientId, $data['broker']);
                if (!$client->getCompany()->isEu()) {
                    continue;
                }

                $equities = $this
                    ->equitySvc
                    ->getAccountEquity(new AccountNumber($accNo));

                $rec2 = end($equities);

                $dt = DateTime::of($data["payout_interval_start"])->currHour();
                while (false !== prev($equities) && DateTime::of(current($equities)["date_time"]) > $dt) {}
                $rec1 = next($equities);

                $profit = round(100.0 * ($rec2["unit_price"] - $rec1["unit_price"]) / $rec1["unit_price"], 2);
                if ($profit >= 0) {
                    continue;
                }

                $currLevel = -1 * intval($profit / $step);
                if ($currLevel === 0) {
                    continue;
                }

                $select->execute([$accNo]);
                $notifiedLevel = intval($select->fetch(PDO::FETCH_COLUMN));
                if ($currLevel <= $notifiedLevel) {
                    continue;
                }

                $this
                    ->notifGateway
                    ->notifyClient(
                        $clientId,
                        $data['broker'],
                        NotificationGateway::FOLLOWER_ACC_LOSING,
                        [
                            "accNo"   => $accNo,
                            "accCurr" => $data["acc_curr"],
                            "dt1"     => $rec1["date_time"],
                            "equity1" => sprintf("%.2f", $rec1["equity"]),
                            "dt2"     => $rec2["date_time"],
                            "equity2" => sprintf("%.2f", $rec2["equity"]),
                            "percent" => $currLevel * $step,
                        ]
                    );

                $insert->execute([$accNo, $currLevel]);

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

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}
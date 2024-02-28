<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \Exception;
use Throwable;

class ObsoleteAccountsCommand extends Command
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
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:obsolete_accounts')
            ->setDescription('Deletes outdated testing accounts from CT DB')
            ->addOption('broker', 'b', InputOption::VALUE_REQUIRED, "Broker name")
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $broker = $input->getOption('broker');

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

            $this
                ->deleteAccounts($this->getTestAccounts($broker));

            $this->logger->info("Done: %s", [$broker]);
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

    private function getTestAccounts(string $broker) : array
    {
        try {

            $myConnection = $this
                ->factory
                ->getMyConnection($broker);

            return $myConnection
                ->query("
                    SELECT a.login FROM history h
                    JOIN client c ON c.id = h.client_id AND (c.email LIKE '%@forextime.com' OR c.email LIKE '%@alpari.com' OR c.email LIKE '%@alpari.ru')
                    JOIN account a ON h.entity_id = a.id
                    WHERE h.field = 'status_id' AND h.ts > DATE_SUB(NOW(), INTERVAL 100 DAY) AND h.entity = 'account' AND h.value_before = 2 AND h.value != 2
                    ORDER BY h.id DESC
                ")
                ->fetchColumn(0);
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

    private function deleteAccounts(array $accounts) : void
    {
        try {

            $stmtDelLeader = $this
                ->factory
                ->getCTConnection()
                ->prepare("DELETE FROM leader_accounts   WHERE acc_no = ?");

            $stmtDelFollower = $this
                ->factory
                ->getCTConnection()
                ->prepare("DELETE FROM follower_accounts WHERE acc_no = ?");

            foreach ($accounts as $account) {
                $stmtDelFollower->execute([$account]);
                $stmtDelLeader->execute([$account]);
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

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}
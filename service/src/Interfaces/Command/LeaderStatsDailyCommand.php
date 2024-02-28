<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class LeaderStatsDailyCommand extends Command
{

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceFactory
     */
    private $dataSource;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param DataSourceFactory $dataSourceFactory
     */
    public function setDataSource(DataSourceFactory $dataSourceFactory): void
    {
        $this->dataSource = $dataSourceFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:leader_stats_daily')
            ->setDescription('')
            ->setHelp('');
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {

        if(!$this->lock($this->getName())) {
            $output->writeln("Another process is running");
            $this->logger->error("Failed to set lock, another process is running");
            return  -1;
        }

        $this->logger->info("Updating leaders' daily stats");
        $dbConn = $this->dataSource->getCTConnection();
        $dbConn->exec("
            INSERT INTO leader_stats_daily
            SELECT
                la.acc_no,
                DATE(SUBDATE(NOW(), 1)) date,
                IFNULL(a.followers, 0) followers,
                IFNULL(b.funds, 0) funds,
                IFNULL(c.income, 0) income
            FROM leader_accounts la
            LEFT JOIN (
                SELECT lead_acc_no, COUNT(*) followers
                FROM follower_accounts
                WHERE status = 1
                GROUP BY lead_acc_no
            ) a ON a.lead_acc_no = la.acc_no
            LEFT JOIN (
                SELECT fa.lead_acc_no, SUM(fa.equity) funds
                FROM follower_accounts fa
                WHERE fa.status = 1
                GROUP BY fa.lead_acc_no
            ) b ON b.lead_acc_no = la.acc_no
            LEFT JOIN (
                SELECT fa.lead_acc_no, SUM(c.amount) income
                FROM commission c
                JOIN follower_accounts fa ON fa.acc_no = c.acc_no AND fa.status = 1
                WHERE DATE(c.created_at) = DATE(SUBDATE(NOW(), 1))
                GROUP BY fa.lead_acc_no
            ) c ON c.lead_acc_no = la.acc_no
            WHERE la.status = 1
        ");
        $this->logger->info("Done");

        $this->release();
        return 0;
    }

}
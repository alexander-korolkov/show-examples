<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class SynchronizeNewsCommand extends Command
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
        $this->setName('app:synchronize_news')
            ->setDescription('Copies news from CT database to SAS')
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

            $this->saveNews($this->getNews());

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


    private function getNews() : array
    {
        try {

            return $this
                ->factory
                ->getCTConnection()
                ->executeQuery("
                    SELECT acc_no, 
                         title, 
                         text, 
                         reviewed_at 
                    FROM leader_accounts_news
                    WHERE status = 2
                ")
                ->fetchAll(\PDO::FETCH_ASSOC);
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

    private function saveNews(array $news) : void
    {
        foreach (Broker::list() as $broker)
        {

            $sasConn = $this
                ->factory
                ->getSasConnection($broker);

            $stmt = $sasConn
                ->prepare("
                    INSERT INTO ct_leader_accounts_news (
                        acc_no,
                        title,
                        text,
                        reviewed_at
                    ) VALUES (
                        :acc_no,
                        :title,
                        :text,
                        :reviewed_at
                    )
                ");

            $sasConn->beginTransaction();

            try {

                $sasConn->exec("TRUNCATE TABLE ct_leader_accounts_news");

                foreach ($news as $record)
                {
                    $stmt->execute($record);
                }

                $sasConn->commit();
            }
            catch (Throwable $exception) {

                $sasConn->rollBack();

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
<?php

namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use PDO;
use PDOException;
use Symfony\Component\Console\Command\Command;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class AdjustEquitiesCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Connection
     */
    private $dbConn;

    /**
     * NotifyInactiveFollowersCommand constructor.
     * @param LoggerInterface $logger
     * @param DataSourceFactory $factory
     */
    public function __construct(
        LoggerInterface $logger,
        DataSourceFactory $factory
    )
    {
        $this->dbConn = $factory->getCTConnection();
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:adjust_equities')
            ->setDescription('Command to found last leader equity in weekend and paste it as first equity in Monday.')
            ->setHelp('Command to found last leader equity in weekend and paste it as first equity in Monday.')
            ->addArgument('acc_no', InputArgument::OPTIONAL, 'The account number of the user.');
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $accNumberCondition = '';
        if($accNumber = $input->getArgument('acc_no')) {
            $accNumberCondition = 'AND acc_no='.$accNumber;
        }

        $this->logger->info('AdjustEquitiesCommand started.');

        $accountNumbers = $this->dbConn->query("
            SELECT acc_no FROM leader_accounts
            WHERE status = 1
            {$accNumberCondition}
        ")->fetchAll(PDO::FETCH_ASSOC);

        $output->writeln("Leaders found: " . count($accountNumbers) . "\n");

        foreach ($accountNumbers as $accountNumber) {
            $this->adjustEquities($output, $accountNumber['acc_no']);
        }

        $this->logger->info('AdjustEquitiesCommand finished.');
        return 0;
    }


    protected function adjustEquities(OutputInterface $output, $accountNumber)
    {
        $output->writeln("Handling account: {$accountNumber}" . "\n");

        $accNumberCondition = 'AND acc_no='.$accountNumber;

        $equities = $this->dbConn->query("
            SELECT acc_no, e.date_time, equity, in_out FROM equities AS e
            WHERE (WEEKDAY(e.date_time) = 6 OR WEEKDAY(e.date_time) = 5)
            {$accNumberCondition} 
        ")->fetchAll(PDO::FETCH_ASSOC);

        $output->writeln("Equity found:". count($equities) . "\n");

        if (!empty($equities)) {
            $filteredEquities = [];
            foreach ($equities as $equity) {
                $dateTime = new DateTime($equity['date_time']);
                $dateTimeDay = $dateTime->format("Y-m-d");

                if (isset($filteredEquities[$dateTimeDay.':'.$equity['acc_no']])) {
                    $previousDateTime = new DateTime($filteredEquities[$dateTimeDay.':'.$equity['acc_no']]['date_time']);

                    if ($dateTime > $previousDateTime) {
                        $filteredEquities[$dateTimeDay.':'.$equity['acc_no']] = $equity;
                    }
                } else {
                    // Check diff
                    $previousDateTime = clone $dateTime;
                    $previousDateTime = $previousDateTime->modify('-1 day');
                    $previousDateTimeDay = $previousDateTime->format("Y-m-d");

                    if (isset($filteredEquities[$previousDateTimeDay.':'.$equity['acc_no']])) {
                        unset($filteredEquities[$previousDateTimeDay.':'.$equity['acc_no']]);
                    }

                    $filteredEquities[$dateTimeDay.':'.$equity['acc_no']] = $equity;
                }
            }

            $output->writeln("Equities for inserting:". count($filteredEquities) . "\n");

            foreach ($filteredEquities as $equityRow) {
                try {
                    $dateTime = new DateTime($equityRow['date_time']);
                    switch ($dateTime->getWeekdayNumber()) {
                        case 6:
                            $dateTime->modify('+2 days')->setTime('00', '00', '01');
                            break;
                        case 7:
                            $dateTime->modify('+1 day')->setTime('00', '00', '01');
                            break;
                    }

                    $equityRow['date_time'] = $dateTime->__toString();
                    $equityRow['in_out'] = '0.0000';

                    $values = "('" . implode("','", $equityRow) . "')";
                    $insertQuery = "INSERT INTO equities(acc_no, date_time, equity, in_out) VALUES {$values};";

                    $this->dbConn->query($insertQuery);

                    $output->writeln(sprintf("Equity %s for account #%d (%s) inserted.", $equityRow['equity'], $equityRow['acc_no'], $equityRow['date_time']));

                } catch (PDOException $exc) {
                    $this->logger->info('Insertion for equity meet error: {error}', ['error' => $exc->getMessage()]);
                    $output->writeln("Insertion for account {$accountNumber} equity meet error: " . $exc->getMessage() . "\n");
                }
            }
        }
    }
}

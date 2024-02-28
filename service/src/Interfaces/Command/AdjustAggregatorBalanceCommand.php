<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Exception;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class AdjustAggregatorBalanceCommand extends Command
{
    use LockableTrait;

    /**
     * @var Connection
     */
    private $dbConnection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @param Connection $dbConnection
     */
    public function setDbConnection(Connection $dbConnection): void
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param TradeAccountGateway $tradeAccountGateway
     */
    public function setTradeAccountGateway(TradeAccountGateway $tradeAccountGateway): void
    {
        $this->tradeAccountGateway = $tradeAccountGateway;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:adjust_aggregator_balance')
            ->setDescription('Fixes inconsistencies on aggregator\'s balances')
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

        $numberFailedAccount = 0;
        $numberSkippedAccount = 0;
        foreach ($this->getInconsistencies() as $inconsistency) {
            $accountNumber = (string) $inconsistency['aggr_no'];

            // if the diff parameter from the $inconsistency array is negative
            // then we need add this amount to the aggregate account
            // else skip this account
            $amountDifference = (float) $inconsistency['diff'];
            if ($amountDifference >= 0) {
                $numberSkippedAccount++;
                continue;
            }

            // because it's should be a deposit (positive amount)
            $amountDifference *= -1;

            $dataOfLog = [
                'cron_name' => 'ADJUST_AGGREGATOR_BALANCE',
                'account_number' => $accountNumber,
                'amount_difference' => $amountDifference,
            ];

            try {
                $response = $this->tradeAccountGateway->adjustAggregatorBalance($inconsistency['broker'], $accountNumber, $amountDifference);
                if (empty($response)) {
                    throw new RuntimeException('Response from the API CMS is empty');
                }
            } catch (Throwable $exception) {
                $this->logger->error(
                    self::fmt('Change balance operation failed. Account: %s', [$accountNumber]),
                    array_merge($dataOfLog, ['error' => $exception->getMessage()])
                );

                $numberFailedAccount++;

                continue;
            }

            $success = (bool)(filter_var($response['success'], FILTER_VALIDATE_BOOL));
            if (!$success) {
                $this->logger->error(
                    self::fmt('Change balance operation failed. Account: %s', [$accountNumber]),
                    array_merge($dataOfLog, ['error' => $success['error']])
                );

                $numberFailedAccount++;
            }

            $this->logger->info(
                self::fmt('change balance of account %s with amount %s has been executed successfully', [$accountNumber, $amountDifference]),
                array_merge($dataOfLog, ['error' => $success['error']])
            );
        }

        $this->logger->info(
            self::fmt(
                'Finished. Number of failed accounts %d. Number of skipped accounts %d',
                [$numberFailedAccount, $numberSkippedAccount]
            )
        );

        $this->release();

        return 0;
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    private function getInconsistencies(): array
    {
        try {
            $stmt = $this->dbConnection->prepare("
                SELECT
                    la.acc_no 			as lead_no, 
                    la.equity 			as lead_equity,	
                    la.aggr_acc_no 		as aggr_no, 
                    la.aggr_acc_equity	as aggr_equity,
                    la.broker 			as broker,
                    SUM(coalesce(fa.equity, 0)) as foll_equity,
                    (SUM(coalesce(fa.equity, 0)) + la.equity) as expected_agg_equity,
                    ROUND(la.aggr_acc_equity - (SUM(coalesce(fa.equity, 0)) + la.equity), 2) AS diff,
                    la.aggr_acc_equity / (SUM(coalesce(fa.equity, 0)) + la.equity) AS ratio
                FROM leader_accounts as la
                    LEFT JOIN follower_accounts as fa ON fa.lead_acc_no = la.acc_no AND fa.status = 1
                WHERE la.status = 1 AND la.aggr_acc_no IS NOT NULL AND la.is_copied != 0
                GROUP BY la.aggr_acc_no
                HAVING diff < 0.0 AND ratio < 0.95
            ");

            return $stmt->executeQuery()->fetchAllAssociative();
        } catch (Exception $exception) {
            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );
        }

        return [];
    }

    /**
     * @param string $msg
     * @param array $params
     * @return string
     */
    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }
}
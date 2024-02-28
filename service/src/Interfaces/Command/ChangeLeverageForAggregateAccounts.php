<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Doctrine\DBAL\Connection;
use Exception;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChangeLeverageForAggregateAccounts extends Command
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TradeAccountGateway
     */
    private $accGateway;

    /**
     * @var PDO
     */
    private $dbConnection;

    /**
     * ChangeLeverageForAggregateAccounts constructor.
     * @param Connection $dbConnection
     * @param TradeAccountGateway $accGateway
     * @param LoggerInterface $logger
     */
    public function __construct(
        Connection $dbConnection,
        TradeAccountGateway $accGateway,
        LoggerInterface $logger
    ) {
        $this->dbConnection = $dbConnection;
        $this->accGateway = $accGateway;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:change_leverage_for_aggregate_account:change')
            ->setDescription('Command to find aggregate accounts of NEW and ACTIVATED leaders and set leverage 2000.')
            ->setHelp('Command to find aggregate accounts of NEW and ACTIVATED leaders and set leverage 2000.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('ChangeLeverageForAggregateAccounts started.');

        $accounts = $this->getAccountsToChangeLeverage();
        $this->logger->info(sprintf('ChangeLeverageForAggregateAccounts: found %d accounts.', count($accounts)));

        if(!empty($accounts)) {
            foreach ($accounts as $account) {
                $tradeAcc = $this->accGateway->fetchAccountByNumber(new AccountNumber($account["aggr_acc_no"]), $account["broker"]);
                if ($tradeAcc->leverage() >= 2000) {
                    $this->logger->info(sprintf('ChangeLeverageForAggregateAccounts: Leverage of %s is already 2000.', $account["aggr_acc_no"]));
                    continue;
                }
                try {
                    $this->accGateway->changeAccountLeverage(new AccountNumber($account["aggr_acc_no"]), $account["broker"], 2000);
                } catch (Exception $e) {
                    $this->logger->info(sprintf('ChangeLeverageForAggregateAccounts: Something went wrong for aggr_acc = %s', $account["aggr_acc_no"]));
                }
                $this->logger->info(sprintf('ChangeLeverageForAggregateAccounts: Leverage of %s has been set to 2000.', $account["aggr_acc_no"]));
            }
        }

        $this->logger->info('ChangeLeverageForAggregateAccounts finished.');

        return 0;
    }

    private function getAccountsToChangeLeverage()
    {
        $stmt = $this->dbConnection->query("
            SELECT
	            la.aggr_acc_no, la.broker
            FROM leader_accounts la
            WHERE la.status IN (0, 1) AND la.aggr_acc_no IS NOT NULL      
        ");

        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $accounts;
    }
}

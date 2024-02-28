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

class ChangeLeverageForFollowerAccounts extends Command
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
        $this->setName('app:followers_leverage:change')
            ->setDescription('Command to find MT5 followers and set leverage to 5000.')
            ->setHelp('Command to find MT5 followers and set leverage to 5000.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('ChangeLeverageForFollowerAccounts started.');

        $accounts = $this->getAccountsToChangeLeverage();
        $this->logger->info(sprintf('ChangeLeverageForFollowerAccounts: found %d accounts.', count($accounts)));

        if(!empty($accounts)) {
            foreach ($accounts as $account) {
                $tradeAcc = $this->accGateway->fetchAccountByNumber(new AccountNumber($account["acc_no"]), $account["broker"]);
                if ($tradeAcc->leverage() >= 5000) {
                    $this->logger->info(sprintf('ChangeLeverageForFollowerAccounts: Leverage of %s is already 5000.', $account["acc_no"]));
                    continue;
                }
                try {
                    $this->accGateway->changeAccountLeverage(new AccountNumber($account["acc_no"]), $account["broker"], 5000);
                } catch (Exception $e) {
                    $this->logger->info(sprintf(
                        'ChangeLeverageForFollowerAccounts: Something went wrong for acc %s, %s',
                        $account["acc_no"],
                        $e->getMessage()
                    ));
                }
                $this->logger->info(sprintf('ChangeLeverageForFollowerAccounts: Leverage of %s has been set to 5000.', $account["acc_no"]));
            }
        }

        $this->logger->info('ChangeLeverageForFollowerAccounts finished.');

        return 0;
    }

    private function getAccountsToChangeLeverage()
    {
        $stmt = $this->dbConnection->query("
            SELECT
	            fa.acc_no, fa.broker
            FROM follower_accounts fa
            WHERE fa.status IN (0, 1) AND fa.server IN (4,5)    
        ");

        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $accounts;
    }
}

<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;


class FixInconsistentAggregateAccountsCommand extends Command
{

    /**
     * @var Connection
     */
    private $myFXConnection;

    /**
     * @var Connection
     */
    private $myAIConnection;

    /**
     * @var Connection
     */
    private $FRSConnection;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Connection $myFXConnection
     */
    public function setMyFXConnection(Connection $myFXConnection): void
    {
        $this->myFXConnection = $myFXConnection;
    }

    /**
     * @param Connection $myAIConnection
     */
    public function setMyAIConnection(Connection $myAIConnection): void
    {
        $this->myAIConnection = $myAIConnection;
    }

    /**
     * @param Connection $MRSConnection
     */
    public function setFRSConnection(Connection $MRSConnection): void
    {
        $this->FRSConnection = $MRSConnection;
    }

    /**
     * @param TradeAccountGateway $tradeAccountGateway
     */
    public function setTradeAccountGateway(TradeAccountGateway $tradeAccountGateway): void
    {
        $this->tradeAccountGateway = $tradeAccountGateway;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:fix-aggregator-country')
            ->setDescription('Fixes inconsistent county for aggregator accounts')
            ->setHelp('Fixes inconsistencies between web and plugin databases')
            ->addOption('debug',null,InputOption::VALUE_OPTIONAL, 'Do not create workflows');
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $debugMode = !empty($input->getOption('debug'));
        $status = ['processed' => 0, 'failed' => 0, 'skipped' => 0];
        try {
            $this->logger->info("FixInconsistentAggregateAccountsCommand: Started");
            foreach ($this->findInconsistencies() as $list) {
                $tmp = $this->fixInconsistencies($list, $debugMode);
                $status['processed'] += $tmp['processed'];
                $status['failed'] += $tmp['failed'];
                $status['skipped'] += $tmp['skipped'];
            }
        }
        catch (\Exception $exception) {
            $this->logger->error("FixInconsistentAggregateAccountsCommand: Exception: {$exception}");
        }

        if($status['failed'] == 0) {
            $this->logger->info("FixInconsistentAggregateAccountsCommand: Done: Processed: {$status['processed']}; Failed: {$status['failed']}; Skipped: {$status['skipped']}");
        }
        else {
            $this->logger->warning("FixInconsistentAggregateAccountsCommand: Done: Processed: {$status['processed']}; Failed: {$status['failed']}; Skipped: {$status['skipped']}");
        }
    }

    /**
     * @return \Generator
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getAllMyAggregatorAccounts()
    {
        $stmt = $this->myFXConnection->prepare("
                SELECT 
                    a.login,
                    ctr.name AS `country`
                FROM `account` AS a
                    LEFT OUTER JOIN `account_type` AS atype ON a.account_type_id = atype.id
                    LEFT OUTER JOIN `client` AS c ON a.client_id = c.id
                    LEFT OUTER JOIN `country` AS ctr ON c.country_reg_id = ctr.id
                WHERE
                    a.account_type_id IN (47, 48)
        ");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            yield ['login' => intval($row['login']), 'country' => $row['country']];
        }

        $stmt = $this->myAIConnection->prepare("
                SELECT 
                    a.login,
                    ctr.name AS `country`
                FROM `account` AS a
                    LEFT OUTER JOIN `account_type` AS atype ON a.account_type_id = atype.id
                    LEFT OUTER JOIN `client` AS c ON a.client_id = c.id
                    LEFT OUTER JOIN `country` AS ctr ON c.country_reg_id = ctr.id
                WHERE
                    a.account_type_id IN (10020)
        ");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            yield ['login' => strval($row['login']), 'country' => $row['country']];
        }
    }

    /**
     * @param array $logins
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getFRSCountries(array $logins)
    {
        if(count($logins) == 0) {
            return [];
        }

        $questionMarks = implode(', ', array_fill(0, count($logins), '?'));
        $stmt = $this->FRSConnection->prepare(
            sprintf("
                SELECT 
                    users.Login AS `login`,
                    users.Country AS `country`
                FROM mt5_user AS users 
                WHERE users.Login IN (%s)         
            ", $questionMarks)
        );
        $stmt->execute($logins);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[strval($row['login'])] = $row['country'];
        }
        return $result;
    }

    /**
     * @param array $my
     * @param array $mrs
     * @return array
     */
    private function compareMaps(array $my, array $mrs)
    {
        $result = [];
        foreach ($my as $login => $myCountry) {
            if(!isset($mrs[$login])) {
                continue;
            }
            $mrsCountry = $mrs[$login];
            if($mrsCountry == $myCountry) {
                continue;
            }
            if(strlen($mrsCountry) < strlen($myCountry)) {
                if(str_starts_with($myCountry, $mrsCountry)) {
                    continue;
                }
            }
            $result[] = [
                'login' => $login,
                'new' => $myCountry,
                'old' => $mrsCountry
            ];
        }
        return $result;
    }

    /**
     * @return \Generator
     * @throws \Doctrine\DBAL\DBALException
     */
    private function findInconsistencies()
    {
        $chunk = [];
        foreach ($this->getAllMyAggregatorAccounts() as $account) {
            $chunk[$account['login']] = $account['country'];
            if(count($chunk) >= 1000) {
                yield $this->compareMaps($chunk, $this->getFRSCountries(array_keys($chunk)));
                $chunk = [];
            }
        }
    }

    /**
     * @param array $list
     * @param bool $debug
     * @return array
     */
    private function fixInconsistencies(array $list, bool $debug)
    {
        $counter = ['processed' => 0, 'failed' => 0, 'skipped' => 0];
        if(count($list) == 0) {
            return $counter;
        }
        foreach ($list as $row) {

            if($debug) {
                echo "{$row['login']}\t{$row['old']}\t{$row['new']}\n";
                $counter['skipped'] += 1;
                continue;
            }

            $this->logger->warning("FixInconsistentAggregateAccountsCommand: Found account: {$row['login']} old value {$row['old']} new value {$row['new']}");
            try {
                $this
                    ->tradeAccountGateway
                    // Aggregate account is always on fxtm side
                    ->refresh(new AccountNumber(intval($row['login'])), 'fxtm');
                $this->logger->warning("FixInconsistentAggregateAccountsCommand: Account have been changed: {$row['login']} old value {$row['old']} new value {$row['new']}");
                $counter['processed'] += 1;
            }
            catch (\Exception $exception) {
                $this->logger->error("FixInconsistentAggregateAccountsCommand: Failed to update: {$row['login']}: {$exception}");
                $counter['failed'] += 1;
            }
        }
        return $counter;
    }
}
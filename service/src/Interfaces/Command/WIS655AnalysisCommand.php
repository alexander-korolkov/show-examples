<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use ClickHouseDB\Client;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\DbConnector\ClickHouseConnector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WIS655AnalysisCommand extends Command
{

    /**
     * @var LeaderAccountRepository
     */
    private $leadersRepository;

    /**
     * @var ClickHouseConnector
     */
    public $clickHouseConnector;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function setLeadersAccountRepository(LeaderAccountRepository $repository) : void
    {
        $this->leadersRepository = $repository;
    }

    public function setClickHouseConnector(ClickHouseConnector $connector) : void
    {
        $this->clickHouseConnector = $connector;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:wis655anal')
            ->setDescription('Compares equities with account candles data from ClickHouse DB')
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info("Collecting list of accounts");
        $timestamp = DateTime::NOW()->getTimestamp();
        $leadersRows = $this->leadersRepository->getForCalculatingStats();
        $results = [];
        $accountsByServer = [];
        foreach ($leadersRows as $row) {
            if (
                !in_array(
                    intval($row['server']),
                    [
                        Server::ECN,
                        Server::ECN_ZERO,
                    ]
                )
            ) {
                $this->logger->warning("Skipped account: {$row['acc_no']} (Wrong server)");
                continue;
            }
            $accNo = new AccountNumber($row['acc_no']);
            try {
                $leader = $this->leadersRepository->find($accNo);
            }
            catch (\Throwable $any) {
                $this->logger->warning("Skipped account: {$row['acc_no']} (Repository fault)");
                continue;
            }
            $timeDelta = DateTime::NOW()->getTimestamp() - $timestamp;
            if(!$leader ||  abs($leader->equity()->amount()) < 0.5) {
                $this->logger->warning("Skipped account: {$row['acc_no']} (low equity)");
                continue;
            }
            $results[strval($row['acc_no'])] = [
                'account'   => $row['acc_no'],
                'equity_gw' => $leader->equity()->amount(),
                'equity_ac' => 0.0,
                'time_delta' => $timeDelta
            ];
            if(!isset($accountsByServer[$leader->server()])) {
                $accountsByServer[$leader->server()] = [];
            }
            $accountsByServer[$leader->server()][] = $row['acc_no'];
            if($timeDelta > 120) {
                break;
            }
        }
        $this->logger->info("Collecting equities from CH");
        foreach ($accountsByServer as $server => $accounts) {
            $logins = implode(', ', $accounts);
            $client = $this->clickHouseConnector->getConnection($server);
            $stmt = $client->select(
                "
                    SELECT
                        candels.login as login,
                        candels.equity_open as `equity`
                    FROM mt4_account_candles_history as candels
                        RIGHT JOIN (
                            SELECT 
                                ach.login as login, 
                                MAX(ach.candletime) as recent 
                            FROM mt4_account_candles_history as ach
                            WHERE
                                ach.login in ({$logins})
                                AND toDayOfWeek(toDate(ach.candletime)) NOT IN (6, 7)
                                AND ach.candletime <= {$timestamp}
                            GROUP BY ach.login
                        ) as flt ON flt.login = candels.login AND flt.recent = candels.candletime
                    WHERE candels.login in ({$logins})                
                "
            );
            while (($row = $stmt->fetchRow()) != null) {
                $results[strval($row['login'])]['equity_ac'] = floatval($row['equity']);
            }
        }
        $this->logger->info("Done");
        $io = new SymfonyStyle($input, $output);
        $io->table(['Leader', 'Equity GW', 'Equity AC', 'Time delta'], $results);
        return 0;
    }

}
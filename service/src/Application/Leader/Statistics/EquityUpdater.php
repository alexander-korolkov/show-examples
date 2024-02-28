<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\Leader\DisconnectNotActiveLeaderWorkflow;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\LoggingTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\MemoryUsageTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\OptionsTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\SettingsTrait;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Interfaces\Persistence\Query\MultipleInsert;
use Psr\Log\LoggerInterface;

class EquityUpdater
{
    use OptionsTrait;
    use LoggingTrait;
    use MemoryUsageTrait;
    use SettingsTrait;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var EquityService
     */
    private $equityService;

    /**
     * @var Connection
     */
    private $dbConnection;

    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * EquityUpdater constructor.
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param EquityService $equityService
     * @param Connection $dbConnection
     * @param SettingsRegistry $settingsRegistry
     * @param Timer $timer
     * @param LoggerInterface $logger
     */
    public function __construct(
        LeaderAccountRepository $leaderAccountRepository,
        EquityService $equityService,
        Connection $dbConnection,
        SettingsRegistry $settingsRegistry,
        Timer $timer,
        LoggerInterface $logger,
        WorkflowManager $workflowManager
    ) {
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->equityService = $equityService;
        $this->dbConnection = $dbConnection;
        $this->timer = $timer;
        $this->workflowManager = $workflowManager;

        $this->setLogger($logger);
        $this->setSettingsRegistry($settingsRegistry);
        $this->setSourceName('LeaderEquityStats_EquityUpdater');
    }

    /**
     * Method gets equity changes for all active leader accounts, its aggregate accounts and follower accounts
     * from MT servers
     * ans saves it in equities table
     *
     * @previous equities.php script
     * @param array $options
     */
    public function run(array $options = [])
    {
        $processTime = DateTime::NOW();
        $this->setOptions($options);

        if (!$this->shouldUpdateEquities($processTime)) {
            $this->log('Equities shouldn\'t be updated at this hour.');
            return;
        }

        $this->log('Equities updating started.');
        $this->timer->clear();
        $this->timer->start();

        try {
            $accounts = $this->leaderAccountRepository->getActiveWithAggregatesAndFollowers();
            $this->timer->measure('get_accounts');
            $this->memoryUsage('updateEquities[get_accounts]');

            /* get exists equities */
            $equitiesList = $this->equityService->getAccountsEquityFormWebGate($accounts, $processTime);

            /* collect all accounts numbers list */
            $accountsNumbers = [];
            array_walk($accounts, static function($account) use (&$accountsNumbers) {
                array_walk($account, static function($accountData, $accountNumber) use (&$accountsNumbers) {
                    $accountsNumbers[$accountNumber] = $accountData;
                });
            });

            /* collect accounts numbers which do not have equity from FRS/ARS */
            $emptyEquities = array_diff_key($accountsNumbers, $equitiesList);

            /* get last values for empty equities from local DB */
            $equitiesFromLocalDd = $this->equityService->getLastEquitiesByAccountsNumbersFromLocalDb(
                array_keys($emptyEquities)
            );

            $equities = array_replace($equitiesList, $equitiesFromLocalDd);

            $this->timer->measure('get_equities');
            $this->memoryUsage('updateEquities[get_equities]');

            $massInsertStatement = new MultipleInsert('equities', ['acc_no', 'date_time', 'equity']);
            foreach (array_chunk($equities, 100, true) as $chunk) {
                $massInsertStatement->execute(
                    $this->dbConnection,
                    $this->prepareEquityForMassInsert($chunk, $processTime)
                );
                $this->log('Executed mass equity insert of accounts: ' . implode(', ', array_keys($chunk)));
                $this->timer->measure('cycle_insert');
            }

            $this->actOnZeroEquities($accounts, $equities);

            $this->saveSettingsToRegistry('stats.equities.last_update', $processTime);
        } catch (\Exception $e) {
            $this->log(sprintf('Equities updating failed with error: %s', $e->getMessage()), 'error');
            return;
        }

        $this->log('Equities updating finished.');
        $this->log(sprintf('Time measurements: %s', json_encode($this->timer->averageTimes())), 'debug');
    }

    private function actOnZeroEquities(array $servers, array $equities): void
    {
        $delaySeconds = 0;
        foreach ($servers as $accounts) {
            foreach ($accounts as $accountNumber => $accountData) {
                try {
                    if (
                        $accountData[LeaderAccountRepository::KEY_TYPE] == LeaderAccountRepository::ACCOUNT_TYPE_LEADER &&
                        array_key_exists($accountNumber, $equities) && $equities[$accountNumber] <= 0.0
                    ) {
                        $this->enqueueDisconnectNotActiveLeaderWorkflow(
                            $accountNumber,
                            $accountData[LeaderAccountRepository::KEY_BROKER],
                            $delaySeconds
                        );
                        $delaySeconds += 10;
                    }
                } catch (\Throwable $e) {
                    $this->log(
                        sprintf('Act on zero equities for account %s error: %s', $accountNumber, $e->getMessage()),
                        'error'
                    );
                }
            }
        }
    }

    private function enqueueDisconnectNotActiveLeaderWorkflow(string $accountNumber, string $broker, int $delaySeconds): void
    {
        $closeAccountWorkflow = $this->workflowManager->newWorkflow(
            DisconnectNotActiveLeaderWorkflow::TYPE,
            new ContextData([
                'accNo' => $accountNumber,
                ContextData::REASON => DisconnectNotActiveLeaderWorkflow::REASON_LOST_ALL_MONEY,
                ContextData::KEY_BROKER => $broker,
            ])
        );
        $closeAccountWorkflow->scheduleAt(DateTime::of("+$delaySeconds seconds"));
        $this->workflowManager->enqueueWorkflow($closeAccountWorkflow);

        $this->logger->info(sprintf(
            'EquityUpdater: workflow to disconnect not active leader %s has been created.',
            $accountNumber
        ));
    }

    /**
     * Defines should the Updater to update equities
     *
     * @param DateTime $processTime
     * @return bool
     */
    private function shouldUpdateEquities(DateTime $processTime): bool
    {
        return $this->forceUpdate() ||
               !$this->alreadyExecutedForThisHour('equities', $processTime);
    }

    /**
     * Method prepares array 0f equities
     * for mass inserting into db
     *
     * @param array $equities
     * @param DateTime $processTime
     * @return array
     */
    private function prepareEquityForMassInsert(array $equities, DateTime $processTime): array
    {
        $prepared = [];
        foreach ($equities as $accountNumber => $equity) {
            $prepared[] = [
                'acc_no' => $accountNumber,
                'date_time' => $processTime,
                'equity' => $equity
            ];
        }

        return $prepared;
    }
}

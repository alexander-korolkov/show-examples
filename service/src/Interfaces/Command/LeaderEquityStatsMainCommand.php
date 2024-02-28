<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\Leader\Statistics\LeaderEquityStatsSaver;
use Fxtm\CopyTrading\Application\Leader\Statistics\LeadersNotificator;
use Fxtm\CopyTrading\Application\Leader\Statistics\RatingsShaper;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\SettingsTrait;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\Process;

class LeaderEquityStatsMainCommand extends LeaderEquityStatsBaseCommand
{
    use SettingsTrait;

    private const PHP_COMMAND = 'php';
    private const WORKERS_OPTION = 'workers';

    public const MAX_NUMBER_OF_PROCESSES = 20;

    /**
     * @var LeaderEquityStatsSaver
     */
    private $statsSaver;

    /**
     * @var LeadersNotificator
     */
    private $leadersNotificator;

    /**
     * @var RatingsShaper
     */
    private $ratingsShaper;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var bool
     */
    private $running = true;

    /**
     * @var LockFactory
     */
    private $locker;

    public function __construct(
        LeaderEquityStatsSaver $statsSaver,
        LeadersNotificator $leadersNotificator,
        RatingsShaper $ratingsShaper,
        LeaderAccountRepository $leaderAccountRepository,
        Timer $timer,
        RedisAdapter $cacheRedis,
        LoggerInterface $logger,
        LockFactory $lockFactory,
        string $name = null
    ) {
        $this->statsSaver = $statsSaver;
        $this->leadersNotificator = $leadersNotificator;
        $this->ratingsShaper = $ratingsShaper;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->timer = $timer;
        $this->locker = $lockFactory->createLock(self::MAIN_PROCESS_COMMAND . '_lock');
        parent::__construct($cacheRedis, $logger, $name);
    }

    protected function configure()
    {
        $this
            ->setName(self::MAIN_PROCESS_COMMAND)
            ->setDescription('Calls the main process for equity stats collection')
            ->addOption(
                self::WORKERS_OPTION,
                'w',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of workers; Default = ',
                self::MAX_NUMBER_OF_PROCESSES
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = intval($input->getOption(self::WORKERS_OPTION));
        $chunkSize = intval($input->getArgument(self::CHUNK_SIZE_ARGUMENT));

        if ($limit <= 0 || $chunkSize  <= 0) {
            $output->writeln(self::ARGS_NOT_VALID_ERROR);
            return 0;
        }

        if (!$this->locker->acquire()) {
            $this->logFmt("Failed to set lock for " .
                          self::MAIN_PROCESS_COMMAND .
                          ", another process is running");
            return -1;
        }
        $this->logFmt('Executing LeaderMain');

        try {
            $this->runMainExecution($limit, $chunkSize, $this->fillOptions($input));

            $status = 0;
        } catch (\Throwable $t) {
            $this->logFmt(
                "Exception occurred: %s\n%s",
                [$t->getMessage(), $t->getTraceAsString()],
                'critical'
            );

            $status = -1;
        } finally {
            $this->locker->release();
        }

        return $status;
    }

    private function runMainExecution(
        int $processLimit,
        int $chunkSize,
        array $options = []
    ) {
        $processTime = DateTime::NOW();
        $this->setOptions($options);

        if (!$this->shouldUpdateEquityStats($processTime)) {
            $this->logFmt('Leader equity stats shouldn\'t be updated at this hour.');
            return;
        }

        $this->logFmt('updateEquityStats process started.');
        $this->timer->clear();
        $this->timer->start();

        try {
            $this->statsSaver->cleanTemporaryTables();
            $this->timer->measure('clear_temp_data');
            $this->logFmt('updateEquityStats[clear_temp_data] - done.');

            $accountsIds = $this->leaderAccountRepository->getAccountsIds($this->getOnlyAccountsOption());
            if (empty($accountsIds)) {
                $this->logFmt('updateEquityStats not found any accounts to handle.');
                return;
            }

            $this->timer->measure('get_accounts');
            $this->memoryUsage('updateEquityStats[get_accounts]');
            $this->logFmt('Found %d accounts', [count($accountsIds)]);

            $processes = array_fill(0, $processLimit, null);

            $processedAccounts = [];
            for ($chunkStart = 0; $chunkStart < count($accountsIds); $chunkStart += $chunkSize) {
                $isStartedProcessing = false;
                do {
                    foreach ($processes as $index => $process) {
                        if (is_null($process)) {
                            $processes[$index] = $this->createProcess(
                                $index,
                                array_slice($accountsIds, $chunkStart, $chunkSize)
                            );
                            $isStartedProcessing = true;
                            $this->logFmt(
                                "Started process for chunk " .
                                ($chunkStart / $chunkSize + 1) .  // TODO: Wrong chunk number
                                " (PID: {$processes[$index]->getPid()})"
                            );
                            break;
                        }
                    }

                    $processedAccounts = array_merge(
                        $processedAccounts,
                        $this->checkFinishedProcesses($processes)
                    );
                } while (!$isStartedProcessing);
            }
            $isProcessingFinished = false;
            while (!$isProcessingFinished) {
                $processedAccounts = array_merge(
                    $processedAccounts,
                    $this->checkFinishedProcesses($processes)
                );
                $isProcessingFinished = empty(
                    array_filter($processes, function ($a) {
                        return $a !== null;
                    })
                );
            }

            $this->timer->measure('chunk_processing');
            $this->executePostSteps($processedAccounts);
            $this->saveSettingsToRegistry('stats.leader_equity_stats.last_update', $processTime);
        } catch (\Throwable $e) {
            $this->logFmt(
                "updateEquityStats process failed with error: %s.\n Stack Trace: %s",
                [$e->getMessage(), $e->getTraceAsString()],
                'error'
            );
            return;
        }

        $this->logFmt('updateEquityStats process finished.');
        $this->logFmt('Time measurements: %s', [json_encode($this->timer->averageTimes())]);
    }

    private function executePostSteps(array $accounts)
    {
        $this->log('updateEquityStats[main_cycle] - done.');
        $this->timer->start();

        try {
            $this->ratingsShaper->shapeRatings($accounts);
            $this->timer->measure('shape_ratings');
            $this->log('updateEquityStats[shape_ratings] - done.');

            $this->leadersNotificator->sendNotifications($accounts);
            $this->timer->measure('send_notification');
            $this->log('updateEquityStats[send_notification] - done.');

            $this->statsSaver->moveTemporaryData($accounts);
            $this->timer->measure('clear_databases');
            $this->log('updateEquityStats[clear_databases] - done.');

            $this->statsSaver->moveChartsFromTmpDirectory();
            $this->timer->measure('moving_charts_from_tmp_directory');
            $this->log('updateEquityStats[moving_charts_from_tmp_directory] - done.');
        } catch (\Exception $e) {
            $this->logFmt($e->getMessage());
        }

        $this->logFmt('Time measurements: %s', [json_encode($this->timer->averageTimes())]);
    }

    private function checkFinishedProcesses(array &$processes): array
    {
        $processedAccountIds = [];
        /** @var Process $process */
        foreach ($processes as $index => $process) {
            if (!is_null($process) && !$process->isRunning()) {
                try {
                    /** @var CacheItemInterface $cacheItem */
                    $cacheItemId = self::REDIS_PREFIX . self::REDIS_SUB_SUFFIX . $index;
                    $cacheItem = $this->cacheRedis->getItem($cacheItemId);
                    if ($cacheItem->isHit()) {
                        $processedAccountIds = $cacheItem->get();
                        $this->cacheRedis->deleteItem($cacheItemId);
                    }
                    else {
                        $this->logFmt('Cache had been outdated and removed: key = ' . $cacheItemId, [], 'error');
                    }
                } catch (InvalidArgumentException $e) {
                    $this->logFmt(self::CACHE_ERROR, [], 'error');
                }
                if (!empty($process->getOutput())) {
                    $this->logFmt($process->getOutput());
                }
                $processes[$index] = null;
            }
        }
        return $processedAccountIds;
    }

    /**
     * Defines should the Collector to update leader equity stats
     *
     * @param DateTime $processTime
     * @return bool
     */
    private function shouldUpdateEquityStats(DateTime $processTime): bool
    {
        return $this->forceUpdate()
            || (
                !$this->alreadyExecutedForThisHour('leader_equity_stats', $processTime) &&
                $this->alreadyExecutedForThisHour('equities', $processTime)
            );
    }

    /**
     * This option can to limit
     * array of accounts which stats will be updated
     *
     * @return array
     */
    private function getOnlyAccountsOption(): array
    {
        return (array) ($this->getOptions()[self::ACCOUNT_ARGUMENT] ?? []);
    }

    /**
     * Creates and runs sub process
     * @param array $accountIds
     * @return Process
     */
    private function createProcess(int $index, array $accountIds): Process
    {
        $processCommand = array_merge(
            [
                'php',
                '/home/site/ct-api/current/bin/console',
                self::SUB_PROCESS_COMMAND,
                $index
            ],
            $this->getArgsFromOptions()
        );

        try {
            /** @var CacheItemInterface $cacheItem */
            $cacheItem = $this->cacheRedis->getItem(self::REDIS_PREFIX . self::REDIS_MAIN_SUFFIX . $index);
            $cacheItem->set($accountIds);
            $cacheItem->expiresAfter(3600);
            $this->cacheRedis->save($cacheItem);
        } catch (InvalidArgumentException $e) {
            $this->logFmt(self::CACHE_ERROR, [], 'error');
        }

        $process = new Process($processCommand, getcwd());
        $process->setTimeout(null);
        $process->start();

        return $process;
    }

    private function getArgsFromOptions(): array
    {
        $result = [];
        foreach ($this->getOptions() as $option => $value) {
            if (is_bool($value)) {
                $result[] = '--' . $option;
            } else {
                $result[] = '--' . $option . ' ' . $value;
            }
        }
        return $result;
    }
}

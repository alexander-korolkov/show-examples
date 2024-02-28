<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics;

use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\LoggingTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\MemoryUsageTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\OptionsTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\SettingsTrait;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;

class LeaderEquityStatsCollector
{
    /**
     * @var LeaderEquityStatsCalculator
     */
    private $calculator;

    /**
     * @var LeaderEquityStatsSaver
     */
    private $statsSaver;

    /**
     * @var RatingsShaper
     */
    private $ratingsShaper;

    /**
     * @var LeadersNotificator
     */
    private $leadersNotificator;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var EquityService
     */
    private $equityService;

    /**
     * @var Timer
     */
    private $timer;

    use OptionsTrait, LoggingTrait, MemoryUsageTrait, SettingsTrait;

    /**
     * LeaderEquityStatsCollector constructor.
     * @param LeaderEquityStatsCalculator $calculator
     * @param LeaderEquityStatsSaver $statsSaver
     * @param RatingsShaper $ratingsShaper
     * @param LeadersNotificator $leadersNotificator
     * @param SettingsRegistry $settingsRegistry
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param EquityService $equityService
     * @param Logger $logger
     * @param Timer $timer
     */
    public function __construct(
        LeaderEquityStatsCalculator $calculator,
        LeaderEquityStatsSaver $statsSaver,
        RatingsShaper $ratingsShaper,
        LeadersNotificator $leadersNotificator,
        SettingsRegistry $settingsRegistry,
        LeaderAccountRepository $leaderAccountRepository,
        EquityService $equityService,
        Logger $logger,
        Timer $timer
    ) {
        $this->calculator = $calculator;
        $this->statsSaver = $statsSaver;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->equityService = $equityService;
        $this->timer = $timer;
        $this->ratingsShaper = $ratingsShaper;
        $this->leadersNotificator = $leadersNotificator;

        $this->setLogger($logger);
        $this->setSettingsRegistry($settingsRegistry);
        $this->setSourceName('LeaderEquityStatsCollector');
    }

    /**
     * @previous leader_equity_stats.php script
     * @param array $options
     */
    public function run(array $options = [])
    {
        $processTime = DateTime::NOW();
        $this->setOptions($options);

        if(!$this->shouldUpdateEquityStats($processTime)) {
            $this->log('Leader equity stats shouldn\'t be updated at this hour.');
            return;
        }

        $this->log('updateEquityStats process started.');
        $this->timer->clear();
        $this->timer->start();

        try {
            $this->statsSaver->cleanTemporaryTables();
            $this->timer->measure('clear_temp_data');
            $this->log('updateEquityStats[clear_temp_data] - done.');

            $accounts = $this->leaderAccountRepository->getForCalculatingStats($this->getOnlyAccountsOption());
            if (empty($accounts)) {
                $this->log('updateEquityStats not found any accounts to handle.');
                return;
            }

            $this->timer->measure('get_accounts');
            $this->memoryUsage('updateEquityStats[get_accounts]');
            $this->log(sprintf('Found %d accounts', count($accounts)));

            $total = [];
            foreach (array_chunk($accounts, 100) as $chunk) {
                $handled = $this->calculator->calculate($chunk);
                $saved = $this->statsSaver->save($handled, $this->options);
                array_map(function (array &$account)  {
                    unset($account['unit_prices']);
                    unset($account['unit_prices_hourly']);
                }, $saved);
                $total = array_merge($total, $saved);

                $this->log(
                    sprintf(
                        'Handled %d accounts (%.2f%%).',
                        count($total) ,
                        round((count($total) / count($accounts)) * 100, 2)
                    )
                );
                $this->timer->measure('chunk_cycle_iteration');
                $this->memoryUsage('updateEquityStats[chunk_cycle_iteration]');
                $this->log(sprintf('Time measurements: %s', json_encode($this->timer->averageTimes())));
            }

            $this->log('updateEquityStats[main_cycle] - done.');
            $this->timer->start();

            $this->ratingsShaper->shapeRatings($total);
            $this->timer->measure('shape_ratings');
            $this->log('updateEquityStats[shape_ratings] - done.');

            $this->leadersNotificator->sendNotifications($total);
            $this->timer->measure('send_notification');
            $this->log('updateEquityStats[send_notification] - done.');

            $this->statsSaver->moveChartsFromTmpDirectory();
            $this->timer->measure('moving_charts_from_tmp_directory');
            $this->log('updateEquityStats[moving_charts_from_tmp_directory] - done.');

            $this->statsSaver->moveTemporaryData($total);
            $this->timer->measure('clear_databases');
            $this->log('updateEquityStats[clear_databases] - done.');

            $this->saveSettingsToRegistry('stats.leader_equity_stats.last_update', $processTime);
        } catch (\Exception $e) {
            $this->log(sprintf("updateEquityStats process failed with error: %s.\n Stack Trace: %s" , $e->getMessage(), $e->getTraceAsString()), 'error');
            return;
        }

        $this->log('updateEquityStats process finished.');
        $this->log(sprintf('Time measurements: %s', json_encode($this->timer->averageTimes())));
    }

    /**
     * Defines should the Collector to update leader equity stats
     *
     * @param DateTime $processTime
     * @return bool
     */
    private function shouldUpdateEquityStats(DateTime $processTime) : bool
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
    private function getOnlyAccountsOption() : array
    {
        return (array) ($this->getOptions()['account'] ?? []);
    }
}

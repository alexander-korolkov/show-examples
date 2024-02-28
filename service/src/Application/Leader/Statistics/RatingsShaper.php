<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics;

use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\FileStorageGateway;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Interfaces\Persistence\Query\MultipleUpsert;

class RatingsShaper
{
    const RANK_TYPE_EU = 'eu';
    const RANK_TYPE_GLOBAL = 'global';
    const RANK_TYPE_ABY = 'aby';

    /**
     * @var FileStorageGateway
     */
    private $fileStorage;

    /**
     * @var LeaderTopSuitabilityService
     */
    private $leaderTopSuitabilityService;

    /**
     * @var DataSourceFactory
     */
    private $dataSourceFactory;

    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param FileStorageGateway $fileStorage
     * @param LeaderTopSuitabilityService $leaderTopSuitabilityService
     * @param DataSourceFactory $dataSourceFactory
     * @param Timer $timer
     * @param Logger $logger
     */
    public function __construct(
        FileStorageGateway $fileStorage,
        LeaderTopSuitabilityService $leaderTopSuitabilityService,
        DataSourceFactory $dataSourceFactory,
        Timer $timer,
        Logger $logger
    ) {
        $this->fileStorage = $fileStorage;
        $this->leaderTopSuitabilityService = $leaderTopSuitabilityService;
        $this->dataSourceFactory = $dataSourceFactory;
        $this->timer = $timer;
        $this->logger = $logger;
    }


    /**
     * Method defines rating by profit and popularity
     * in array of given accounts
     * and saves it into db
     *
     * @param array $accounts
     * @return array
     * @throws \Exception
     */
    public function shapeRatings(array $accounts)
    {
        $this->timer->start();

        $accounts = $this->shapeProfitRating($accounts);
        $this->timer->measure('sharing_profit_rating');

        $accounts = $this->shapePopularityRating($accounts);
        $this->timer->measure('sharing_popularity_rating');

        $accounts = $this->drawTopCharts($accounts);
        $this->timer->measure('drawing_top_charts');

        $this->saveRatings($accounts);
        $this->timer->measure('saving_ratings_into_db');

        return $accounts;
    }

    /**
     * Method defines places in rating by profit for all given accounts
     * for different companies
     *
     * @param array $accounts
     * @return array
     */
    private function shapeProfitRating(array $accounts)
    {
        $this->sortInDescendingOrderBy($accounts, 'rank_points');

        $rankGlobal = $rankEu = $rankAby = 1;

        foreach ($accounts as &$account) {
            $account['ranks'] = [self::RANK_TYPE_EU => null, self::RANK_TYPE_GLOBAL => null, self::RANK_TYPE_ABY => null];

            if ($this->leaderTopSuitabilityService->isSuitableForTop($account)) {
                if (isset($account['flags']['eu']) && $account['flags']['eu']) {
                    $account['ranks'][self::RANK_TYPE_EU] = $rankEu++;
                } else if (isset($account['flags']['aby']) && $account['flags']['aby']) {
                    $account['ranks'][self::RANK_TYPE_ABY] = $rankAby++;
                } else {
                    $account['ranks'][self::RANK_TYPE_GLOBAL] = $rankGlobal++;
                }
            }
        }

        return $accounts;
    }

    /**
     * Sorts given array in descending order by given $key
     *
     * @param array $array
     * @param $key
     */
    private function sortInDescendingOrderBy(array &$array, $key)
    {
        uasort($array, function ($item1, $item2) use ($key) {
            if ($item1[$key] == $item2[$key]) {
                return 0;
            }

            return ($item1[$key] > $item2[$key]) ? -1 : 1;
        });
    }

    /**
     * Method defines places in rating by popularity for all given accounts
     * for different companies
     *
     * @param array $accounts
     * @return array
     */
    private function shapePopularityRating(array $accounts)
    {
        $this->sortInDescendingOrderBy($accounts, 'pop_points');

        $maxPoint = current($accounts)['pop_points'];
        $popularity = 5;
        $step = $maxPoint / $popularity;

        foreach ($accounts as &$account) {
            if ($account['pop_points'] <= $step * ($popularity - 1)) {
                $popularity = ($account['pop_points'] == 0) ? 0 : $popularity - 1;
                if ($step != 0 && $popularity != ceil($account['pop_points'] / $step)) {
                    $step = $account['pop_points'] / $popularity;
                }
            }
            $account['pop'] = round($popularity, 2);
        }

        return $accounts;
    }

    /**
     * Draws charts for top 3 managers
     * for all companies
     *
     * @param array $accounts
     * @return array
     */
    private function drawTopCharts(array $accounts)
    {
        foreach ($accounts as &$account) {
            if (
                $this->inTopOfCompany($account, self::RANK_TYPE_GLOBAL) ||
                $this->inTopOfCompany($account, self::RANK_TYPE_EU) ||
                $this->inTopOfCompany($account, self::RANK_TYPE_ABY)
            ) {
                $path = $this->saveChart($account['acc_no'], $account['chart_bin']);
                $account['top_chart'] = $path;
            }
        }

        return $accounts;
    }

    /**
     * Saves given chart as file
     *
     * @param $accountNumber
     * @param $chart
     * @return string
     */
    protected function saveChart($accountNumber, $chart)
    {
        $tempDir = "charts/tmp";
        $fileName = '/top_' . $accountNumber . '.png';

        try {
            $this->fileStorage->mkdir($tempDir);
            $this->fileStorage->write($tempDir . $fileName, $chart);
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsRatingShaper: saving top charts failed. Error: ' . $exception->getMessage());
        }

        return '/static/ct/charts' . $fileName;
    }

    /**
     * Returns true if given account in top 3 of
     * given company's rating by profit
     *
     * @param array $account
     * @param $company
     * @return bool
     */
    private function inTopOfCompany(array $account, $company)
    {
        return $account['ranks'][$company] > 0 && $account['ranks'][$company] < 4;
    }

    /**
     * Savings calculated rating into databases
     *
     * @param array $accounts
     * @throws \Exception
     */
    private function saveRatings(array $accounts)
    {
        foreach (array_chunk($accounts, 100) as $chunk) {
            $this->saveRatingsToLeaders($chunk);
            $this->saveRatingsToDatabase($chunk);
        }
    }

    /**
     * Saving ranks for leaders into ct and sas dbs
     *
     * @param array $accounts
     * @throws \Exception
     */
    private function saveRatingsToLeaders(array $accounts)
    {
        $data = [];
        foreach ($accounts as $account) {
            $data[] = [
                'acc_no' => $account['acc_no'],
                'rank' => $account['ranks'][self::RANK_TYPE_GLOBAL] ?? null,
                'pop' => $account['pop'],
            ];
        }
        if (count($data) > 0) {
            $stmt = new MultipleUpsert('leader_equity_stats_tmp', ['acc_no'], ['rank', 'pop']);
            $stmt->execute($this->dataSourceFactory->getCTConnection(), $data);
        }

        $data = [];
        foreach ($accounts as $account) {
            $data[] = [
                'acc_no' => $account['acc_no'],
                'rank' => $account['ranks'][self::RANK_TYPE_GLOBAL] ?? null,
                'pop' => $account['pop'],
                'top_chart' => $account['top_chart'] ?? null,
            ];
        }
        if (count($data) == 0) {
            return;
        }

        $stmt = new MultipleUpsert('ct_leader_equity_stats_tmp', ['acc_no'], ['rank', 'pop', 'top_chart']);
        foreach (Broker::listOfIndependent() as $broker) {
            $stmt->execute($this->dataSourceFactory->getSasConnection($broker), $data, $broker);
        }
    }

    /**
     * Saving ratings into ct and sas databases
     *
     * @param array $accounts
     * @throws \Exception
     */
    private function saveRatingsToDatabase(array $accounts)
    {
        $data = [];
        foreach ($accounts as $account) {
            foreach ($account['ranks'] as $rankType => $rank) {
                $data[] = [
                    'acc_no' => $account['acc_no'],
                    'type' => $rankType,
                    'rank' => $rank,
                ];
            }
        }
        if (count($data) == 0) {
            return;
        }

        $dbRanksStmt = new MultipleUpsert('leader_ranks', ['acc_no', 'type'], ['rank']);
        $dbRanksStmt->execute($this->dataSourceFactory->getCTConnection(), $data);

        $sasDbRanksStmt = new MultipleUpsert('ct_leader_ranks', ['acc_no', 'type'], ['rank']);
        foreach (Broker::listOfIndependent() as $broker) {
            $sasDbRanksStmt->execute($this->dataSourceFactory->getSasConnection($broker), $data, $broker);
        }
    }
}

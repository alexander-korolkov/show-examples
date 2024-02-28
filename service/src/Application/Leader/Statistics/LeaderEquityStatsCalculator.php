<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics;

use Fxtm\CopyTrading\Application\Common\Environment;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\HiddenReason;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Statistics\Utils\PlotDrawingService;

class LeaderEquityStatsCalculator
{
    private const KEY_HIDDEN_REASON = 'hidden_reason';

    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var PlotDrawingService
     */
    private $plotDrawingService;

    /**
     * @var float
     */
    private $thresholdProfitLevel;

    /**
     * @var float
     */
    private $thresholdEquityLevel;

    /**
     * @var float
     */
    private $thresholdAvgEquityLevel;

    /**
     * @var int
     */
    private $thresholdLeverageLevel;

    /**
     * @var EquityService
     */
    private $equityService;

    /**
     * @var string
     */
    private $equityLastUpdateTime;

    /**
     * @var string
     */
    private $minDeposit;

    /**
     * LeaderEquityStatsCalculator constructor.
     * @param Timer $timer
     * @param Logger $logger
     * @param PlotDrawingService $plotDrawingService
     * @param SettingsRegistry $settingsRegistry
     * @param EquityService $equityService
     */
    public function __construct(
        Timer $timer,
        Logger $logger,
        PlotDrawingService $plotDrawingService,
        SettingsRegistry $settingsRegistry,
        EquityService $equityService
    ) {
        $this->timer = $timer;
        $this->logger = $logger;
        $this->plotDrawingService = $plotDrawingService;
        $this->equityService = $equityService;

        $this->thresholdProfitLevel = $settingsRegistry->get('leader.hide_profit_threshold', -90.0);
        $this->thresholdEquityLevel = $settingsRegistry->get('leader.hide_equity_threshold', 1000.0);
        $this->thresholdAvgEquityLevel = $settingsRegistry->get('leader.hide_avg_equity_threshold', 500.0);
        $this->thresholdLeverageLevel = intval($settingsRegistry->get('leader.hide_leverage_threshold', 200));
        $this->equityLastUpdateTime = $settingsRegistry->get('stats.equities.last_update', DateTime::NOW());
        $this->minDeposit = $settingsRegistry->get('follower_acc.min_equity', 100);
    }

    /**
     * Method calculates statistics data
     * for given accounts
     *
     * @param array $accounts
     * @return array
     */
    public function calculate(array $accounts): array
    {
        foreach ($accounts as $i => &$account) {
            $this->timer->start();

            try {
                $equity = $this->equityService->getAccountEquity(new AccountNumber($account['acc_no']));
                $this->timer->measure('get_equities');

                $account['unit_prices_hourly'] = $this->calculateHourlyUnitPrices($equity);
                $this->timer->measure('unit_prices_hourly');

                $newMaxDrawdown = $this->calculateMaxDrawdown($equity);
                $prevMaxDrawdown = $account['prev_max_draw_down'];
                //max_drawdown
                $account['max_drawdown'] = abs($newMaxDrawdown) > abs($prevMaxDrawdown) ?
                    $newMaxDrawdown : $prevMaxDrawdown;
                $this->timer->measure('max_drawdown');

                $maxDrawdown3m = $this->calculateMaxDrawdown($equity, '-3 months');
                $maxDrawdownPoints3m = $this->calculateMaxDrawdownPoints(
                    $maxDrawdown3m,
                    $account['acc_no']
                );
                $this->timer->measure('max_drawdown_points');

                $dailyEquities = $this->calculateDailyEquities($equity);
                $this->timer->measure('daily_equities');

                unset($equity);

                $unitPrices = array_column($dailyEquities, 'unit_price', 'date_time');
                $unitPriceValues = array_values($unitPrices);

                $account['equity'] = $dailyEquities[sizeof($dailyEquities) - 1]['equity'];
                $this->timer->measure('current_equity');

                $account['avg_equity'] = $this->calculateAverageEquity($dailyEquities);
                $this->timer->measure('avg_equity');

                $account['unit_prices'] = $unitPrices;
                $this->timer->measure('unit_prices');

                $account['volatility'] = $this->calculateVolatility($dailyEquities);
                $this->timer->measure('volatility');

                $volatilityPoints = $this->calculateVolatilityPoints($account['volatility'], $account['acc_no']);
                $this->timer->measure('volatility_points');

                $account['profit'] = $this->calculateProfit($unitPrices);
                $this->timer->measure('profit');

                $account['profit_1d'] = $this->calculateProfit($unitPrices, 1);
                $this->timer->measure('profit_1d');

                $account['profit_1w'] = $this->calculateProfit($unitPrices, 7);
                $this->timer->measure('profit_1w');

                $account['profit_1m'] = $this->calculateProfit($unitPrices, 30);
                $this->timer->measure('profit_1m');

                $account['profit_3m'] = $this->calculateProfit($unitPrices, 30 * 3);
                $this->timer->measure('profit_3m');

                $account['profit_6m'] = $this->calculateProfit($unitPrices, 30 * 6);
                $this->timer->measure('profit_6m');

                $this->definePublicFlag($account);

                $account['age_in_days'] = $this->calculateAgeInDays($dailyEquities);
                $this->timer->measure('age_in_days');

                $profitUnprofitDays = $this->calculateProfitUnprofitDays($unitPrices);

                $account['profit_days'] = $profitUnprofitDays['profitDays'];
                $this->timer->measure('profit_days');

                $account['loss_days'] = $profitUnprofitDays['lossDays'];
                $this->timer->measure('loss_days');

                $account['trading_days'] = $profitUnprofitDays['profitDays'] + $profitUnprofitDays['lossDays'];
                $this->timer->measure('trading_days');

                $account['avg_day_profit'] = $this->calculateAverageDailyProfit($unitPriceValues);
                $this->timer->measure('avg_day_profit');

                $account['avg_day_loss'] = $this->calculateAverageDailyLoss($unitPriceValues);
                $this->timer->measure('avg_day_loss');

                $account['avg_day_rate'] = $this->calculateAverageDayRate($account);
                $this->timer->measure('avg_day_rate');

                $account['risk_level_points'] = $this->calculateRiskLevelPoints(
                    $volatilityPoints,
                    $maxDrawdownPoints3m,
                    $account['avg_day_profit']
                );
                $this->timer->measure('risk_level_points');

                $account['risk_level'] = floor($account['risk_level_points']);
                $this->timer->measure('risk_level');

                $account['rank_points'] = $this->calculateRankPoints($account);
                $this->timer->measure('rank_points');

                $account['rank_points_new'] = $this->calculateRankPoints($account);
                $this->timer->measure('rank_points_new');

                $account['pop_points'] = $this->calculatePopPoints($account);
                $this->timer->measure('pop_points');

                $account['manager_name'] = $this->getManagerName($account);
                $this->timer->measure('manager_name');

                $account['country'] = $this->getManagerCountry($account);
                $this->timer->measure('country');

                $account['avatar'] = $this->getManagerAvatar($account);
                $this->timer->measure('avatar');

                $account['chart_bin'] = $this->plotDrawingService->generateChart($unitPriceValues, 200, 50);
                $this->timer->measure('chart_bin');

                $account['privacy_mode'] = $account['is_public'] && $account['is_followable']
                    ? 1
                    : (!$account['is_public'] && $account['is_followable']
                        ? 2
                        : 3
                    );

                $account['chart'] = '';

                $account['total_funds'] = $this->calculateAUM(floatval($account['total_funds_raw']));

                $account['ts'] = $this->equityLastUpdateTime;

                $account['is_veteran'] = $this->calculateIsVeteran($unitPrices);

                $minDeposit = $this->calculateMinDeposit($account['equity']);
                $account['min_deposit_changed'] = false;
                $minDepositInSafetyMode = $this->calculateMinDeposit($account['equity'], true);

                if ($account['min_deposit'] != $minDeposit || $account['min_deposit_in_safety_mode'] != $minDepositInSafetyMode) {
                    $account['min_deposit_changed'] = true;
                    $account['min_deposit'] = $minDeposit;
                    $account['min_deposit_in_safety_mode'] = $minDepositInSafetyMode;
                }

                $this->logger->info(sprintf('LeaderEquityStatsCalculator: handled account %s', $account['acc_no']));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    "LeaderEquityStatsCalculator: Exception '%s' with message '%s' in %s on line %d.",
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
                unset($accounts[$i]);
            }
        }

        return $accounts;
    }

    /**
     * @param array $equity
     * @return array
     */
    private function calculateDailyEquities(array $equity): array
    {
        $daily = [];
        if (empty($equity)) {
            return $daily;
        }

        $daily[] = $equity[0]; // the first element
        $nextDay = DateTime::of($equity[0]['date_time'])->nextDay();
        for ($i = 1; $i < sizeof($equity) - 1; $i++) {
            $dt = DateTime::of($equity[$i]['date_time']);
            if (in_array($dt->getWeekdayNumber(), range(1, 6)) && $dt >= $nextDay) {
                $daily[] = $equity[$i];
            }
            $nextDay = $dt->nextDay();
        }
        $daily[] = $equity[sizeof($equity) - 1]; // the last element

        return $daily;
    }

    /**
     * @param array $dailyEquities
     * @return float
     */
    private function calculateAverageEquity(array $dailyEquities)
    {
        array_shift($dailyEquities); // First elem has equity = 0.0000, we need to remove it
        $dailyEquities = array_filter($dailyEquities, function ($equity) {
            return DateTime::of($equity['date_time']) > new DateTime('-60 days');
        });
        $equities = array_column($dailyEquities, 'equity');

        return count($equities) > 0
            ? round(array_sum($equities) / count($equities), 4)
            : 0;
    }

    /**
     * @param array $equity
     * @return array
     */
    private function calculateHourlyUnitPrices(array $equity): array
    {
        $dt = DateTime::NOW()->relativeDatetime('-30 days');
        $equityChanges = array_filter($equity, function ($row) use ($dt) {
            return DateTime::of($row['date_time']) > $dt && empty(floatval($row['in_out']));
        });

        return array_column($equityChanges, 'unit_price', 'date_time');
    }

    /**
     * @param array $equity
     * @param null $timeDelta
     * @return float
     */
    private function calculateMaxDrawdown(array $equity, $timeDelta = null): float
    {
        $dt = is_null($timeDelta)
            ? null
            : DateTime::NOW()->relativeDatetime($timeDelta);

        $mdd  = 0;
        $peak = 0; // can't be lower

        foreach ($equity as $row) {
            if ((!is_null($dt) && DateTime::of($row['date_time']) <= $dt) || !empty(floatval($row['in_out']))) {
                continue;
            }

            if ($row['unit_price'] > $peak) {
                $peak = $row['unit_price'];
            }

            $dd = 100.0 * ($peak - $row['unit_price']) / $peak;
            if ($dd > $mdd) {
                $mdd = $dd;
            }
        }

        return round($mdd, 2);
    }

    /**
     * @param float $maxDrawdown
     * @return float|null $maxDrawdownPoints
     */
    private function calculateMaxDrawdownPoints($maxDrawdown, $acc_no)
    {
        $levelScale = [
            1 => [0, 15],
            2 => [15, 30],
            3 => [30, 45],
            4 => [45, 60],
            5 => [60, 100],
        ];

        $maxDrawdownPoints = $this->calculatePoints($maxDrawdown, $levelScale);

        $this->logger->info(sprintf(
            "LeaderEquityStatsCalculator: MaxDrawdownPoints points: %s for account number: #%s ",
            $acc_no,
            $maxDrawdownPoints
        ));
        return $maxDrawdownPoints;
    }

    /**
     * @param float $value
     * @return float $points
     */
    private function calculatePoints($value, $levelScale)
    {
        //Save time, return result immediately.
        if ($levelScale[5][1] <= $value) {
            $points = 5.99;
        } elseif ($value == 0) {
            $points = 1;
        } else {
            foreach ($levelScale as $level => $stage) {
                if ($stage[0] < $value && $value <= $stage[1]) {
                    if ($stage[1] == $value) {
                        $points = $level + 0.99;
                    } else {
                        $points = ($value - $stage[0]) / ($stage[1] - $stage[0]) + $level;
                        //cut result to 2 digits after point.
                        $points = floatval(substr($points, 0, 4));
                    }
                }
            }
        }


        return $points;
    }

    /**
     * @param float $volatility
     * @return float $volatilityPoints
     */
    private function calculateVolatilityPoints($volatility, $acc_no)
    {
        $levelScale = [
            1 => [0, 2],
            2 => [2, 3],
            3 => [3, 4],
            4 => [4, 5],
            5 => [5, 200],
        ];

        $volatilityPoints = $this->calculatePoints($volatility, $levelScale);

        $this->logger->info(sprintf(
            "LeaderEquityStatsCalculator: Volatility points: %s for account number: #%s ",
            $acc_no,
            $volatilityPoints
        ));

        return $volatilityPoints;
    }

    /**
     * @param array $unitPricesWithDates
     * @return float|null $volatility
     */
    private function calculateVolatility(array $unitPricesWithDates)
    {
        $changes = [];

        // filter out UP(n) if UP(n) = UP(n-1) (hasn't changed)
        if (count($unitPricesWithDates) >= 2) {
            $changes[] = $unitPricesWithDates[0];
            for ($i = 1; $i < count($unitPricesWithDates); $i++) {
                if (intval(round($unitPricesWithDates[$i]['unit_price'] * 10000.0)) !== intval(round($unitPricesWithDates[$i - 1]['unit_price'] * 10000.0))) {
                    $changes[] = $unitPricesWithDates[$i];
                }
            }
        }

        if (count($changes) < 2) {
            return 0;
        }

        $sums = [];
        for ($i = 1; $i < count($changes); $i++) {
            if (DateTime::of($changes[$i - 1]['date_time'])->isWeekend()) {
                continue;
            }

            $sums[] = abs($changes[$i]['unit_price'] - $changes[$i - 1]['unit_price']) / $changes[$i - 1]['unit_price'];
        }

        return count($sums) > 0 ? round(100.0 * array_sum($sums) / count($sums), 2) : 0;
    }

    /**
     * @param $volatility
     * @return int
     */
    private function calculateRiskLevelPoints($volatility, $maxDrawdown, $avgDayProfit)
    {
        $levelScale = [
            1 => [0, 2],
            2 => [2, 3],
            3 => [3, 4],
            4 => [4, 5],
            5 => [5, 200],
        ];

        $avgDayProfitPoints = $this->calculatePoints($avgDayProfit, $levelScale);

        return max($volatility, $maxDrawdown, $avgDayProfitPoints);
    }

    /**
     * @param array $unitPrices
     * @param null $days
     * @return float|null
     */
    private function calculateProfit(array $unitPrices, $days = null)
    {
        if (empty($unitPrices)) {
            return null;
        }

        $up1 = end($unitPrices);
        $up2 = null;

        if ($days === null) {
            $up2 = reset($unitPrices);
        } elseif ($days === 1) {
            $up2 = prev($unitPrices) ?: null;
        } else {
            $dt = DateTime::of(key($unitPrices))->relativeDatetime("-{$days} days");
            while (false !== prev($unitPrices) && DateTime::of(key($unitPrices)) > $dt) {
            }
            $up2 = current($unitPrices) ?: null;
        }

        return is_null($up2) ? null : round(100.0 * ($up1 - $up2) / $up2, 2);
    }

    /**
     * @param array $unitPrices with daily unit prices and equities
     * @param bool $debug
     * @param null $acc_no
     * @return float monthly
     */
    private function monthlyReturn(array $unitPrices, $debug = false, $acc_no = null)
    {
        $first = reset($unitPrices);
        $prevDate = key($unitPrices);
        $prevMonth = DateTime::of($prevDate)->getMonth();
        next($unitPrices);
        $arrMonthlyEquities = [];

        foreach ($unitPrices as $strDate => $equity) {
            $currMonth = DateTime::of($strDate)->getMonth();
            if ($currMonth != $prevMonth) {
                $arrMonthlyEquities[$strDate] = round(($equity - $first) / $first, 2);
                $first = $equity;
                $prevMonth = $currMonth;
                $prevDate = $strDate;
            }
        }

        // fixes missed value for last month
        if (isset($equity) && isset($strDate) && $prevDate != $strDate) {
            $arrMonthlyEquities[$strDate] = round(($equity - $first) / $first, 2);
        }

        $arrMonthlyEquities = array_reverse($arrMonthlyEquities);

        $value = 1.0;
        $coefficient  = 1.0;
        foreach ($arrMonthlyEquities as $strDate => $unitPrice) {
            // adjustment had been required here https://tw.fxtm.com/servicedesk/view/123851
            if ($unitPrice > 0.4999999) {
                $unitPrice = 0.5;
            }

            //Penalty for no trading time
            if (intval(round($unitPrice * 10000.0)) == 0) {
                if ($debug) {
                    $this->logger->info(sprintf(
                        "LeaderEquityStatsCalculator: Ranking points calculation: %s: Penalty points added: %s",
                        $acc_no,
                        $strDate
                    ));
                }
                $unitPrice = -0.05;
            }

            $value *= (1.0 + $unitPrice * $coefficient);

            if ($debug) {
                $this->logger->info(sprintf(
                    "LeaderEquityStatsCalculator: Ranking points calculation: computing monthly return: %s: %s; coefficient: %.2f; unit price: %.5f; value: %.5f",
                    $acc_no,
                    $strDate,
                    $coefficient,
                    $unitPrice,
                    $value
                ));
            }

            if ($coefficient > 0.1) {
                $coefficient = round($coefficient - 0.05, 2);
            }
        }

        // this update is per discussion here
        // https://tw.fxtm.com/servicedesk/view/115149?p_comment_id=2335894&num=21#comment_2335894
        return ($value - 1) * 100.0;
    }

    /**
     * @param array $account
     */
    private function definePublicFlag(array &$account)
    {
        $account['privacy_mode_changed'] = false;
        $accountHiddenReason = (int) $account[self::KEY_HIDDEN_REASON];

        // Privacy mode has been set manually from the cms. System shouldn't rewrite it
        if ($accountHiddenReason === HiddenReason::BY_COMPANY) {
            return;
        }

        $accountHasLowEquity = $account['equity'] < $this->thresholdEquityLevel ||
            $account['avg_equity'] < $this->thresholdAvgEquityLevel;
        $leverageExceeded = $account['leverage'] > $this->thresholdLeverageLevel;
        $accountNotActive = $account['age_in_days'] > 30 && $account['last_opened_orders_count_30'] == 0;
        $accountProfitInsufficient = $account['profit'] < $this->thresholdProfitLevel;

        if (
            $this->checkPrivacyCondition($account, $accountHasLowEquity, HiddenReason::LOW_EQUITY) ||
            $this->checkPrivacyCondition($account, $leverageExceeded, HiddenReason::HIGH_LEVERAGE) ||
            $this->checkPrivacyCondition($account, $accountNotActive, HiddenReason::NO_ACTIVITY) ||
            $this->checkPrivacyCondition($account, $accountProfitInsufficient, HiddenReason::LOW_PROFIT)
        ) {
            return;
        }

        if (
            !$account['is_public'] &&
            !$accountHasLowEquity &&
            !$leverageExceeded &&
            !$accountNotActive &&
            !$accountProfitInsufficient &&
            $accountHiddenReason !== HiddenReason::BY_CLIENT
        ) {
            $account['is_public'] = true;
            $account['is_followable'] = true;
            $account['privacy_mode_changed'] = true;
            $account[self::KEY_HIDDEN_REASON] = null;
        }
    }

    private function checkPrivacyCondition(
        array &$account,
        bool $predicate,
        int $hiddenReason,
        bool $switchOff = true
    ): bool {
        if (
            $predicate &&
            $account['is_public'] == false &&
            $account[self::KEY_HIDDEN_REASON] == $hiddenReason
        ) {
            //The account is in the correct privacy mode, nothing need to be done. The other checks are not needed
            return true;
        }

        if (
            $predicate &&
            $account[self::KEY_HIDDEN_REASON] != $hiddenReason
        ) {
            $account['is_public'] = false;
            $account['privacy_mode_changed'] = true;
            $account[self::KEY_HIDDEN_REASON] = $hiddenReason;
            $account['notification'] = NotificationGateway::LEADER_ACC_HIDDEN;

            if ($switchOff) {
                $account['is_followable'] = false;
                $account['notification'] = NotificationGateway::LEADER_ACC_SWITCHED_OFF;
            }

            //The privacy mode of the account has been changed, other checks are not needed
            return true;
        }

        //The account doesn't match the given predicate and should be checked by other conditions
        return false;
    }

    /**
     * @param array $dailyEquities
     * @return int
     * @throws \Exception
     */
    private function calculateAgeInDays(array $dailyEquities): int
    {
        return DateTime::NOW()->diff(new DateTime($dailyEquities[0]['date_time']))->days + 1;
    }

    /**
     * @param array $unitPrices
     * @return array
     * @throws \Exception
     */
    private function calculateProfitUnprofitDays(array $unitPrices)
    {
        $unitPricesWithoutCountingCurrentDay = array_slice($unitPrices, 0, count($unitPrices) - 1);
        $days = [
            'profitDays' => 0,
            'lossDays' => 0
        ];
        $unitPricePrev = '';
        foreach ($unitPricesWithoutCountingCurrentDay as $date => $unitPrice) {
            $dt = new DateTime($date);

            if ($unitPricePrev && isset($dtPrev) && !$dtPrev->isWeekend()) {
                if ($unitPrice > $unitPricePrev) {
                    $days['profitDays'] += 1;
                } elseif ($unitPrice < $unitPricePrev) {
                    $days['lossDays'] += 1;
                }
            }

            $dtPrev = $dt;
            $unitPricePrev = $unitPrice;
        }

        return $days;
    }

    /**
     * @param array $unitPrices
     * @return float
     */
    private function calculateAverageDailyProfit(array $unitPrices): float
    {
        $sum = 0;
        $days = 0;

        for ($i = 1; $i < count($unitPrices); $i++) {
            if ($unitPrices[$i] > $unitPrices[$i - 1]) {
                $sum += 100.0 * ($unitPrices[$i] - $unitPrices[$i - 1]) / $unitPrices[$i - 1];
                $days += 1;
            }
        }

        return $days ? round($sum / $days, 2) : 0.0;
    }

    /**
     * @param array $unitPrices
     * @return float
     */
    private function calculateAverageDailyLoss(array $unitPrices): float
    {
        $sum = 0;
        $days = 0;

        for ($i = 1; $i < count($unitPrices); $i++) {
            if ($unitPrices[$i] < $unitPrices[$i - 1]) {
                $sum += 100.0 * ($unitPrices[$i] - $unitPrices[$i - 1]) / $unitPrices[$i - 1];
                $days += 1;
            }
        }

        return $days ? round($sum / $days, 2) : 0.0;
    }

    /**
     * @param array $account
     * @return float
     */
    private function calculateAverageDayRate(array $account): float
    {
        return $account['trading_days'] > 0 ? round($account['profit'] / $account['trading_days'], 2) : 0;
    }

    /**
     * @param array $account
     * @return float|null
     */
    private function calculateRankPoints(array $account)
    {
        if (
            !$account["is_public"] ||
            $account['is_test'] ||
            $account['active_followers_count'] >= 2000
        ) {
            return null;
        }

        switch ($account['risk_level']) {
            case 1:
                $riskCoef = 2.0;
                break;
            case 2:
                $riskCoef = 1.5;
                break;
            case 3:
                $riskCoef = 1.0;
                break;
            case 4:
                $riskCoef = 0.50;
                break;
            case 5:
                $riskCoef = 0.25;
                break;
            default:
                $riskCoef = 0.5;
        }

        $activeDays = 0;
        $unitPrices = array_values($account['unit_prices']);
        if (count($unitPrices) >= 2) {
            for ($i = 1; $i < count($unitPrices); $i++) {
                if ($unitPrices[$i] !== $unitPrices[$i - 1]) {
                    $activeDays++;
                }
            }
        }

        $k  = (1 - $account['remun_fee'] / 100);
        $k *= $riskCoef;
        $k *= 1 + $activeDays / 2000;
        ;
        $k *= (1 - $account['max_drawdown'] / 400);

        $debug = in_array($account['acc_no'], ['1119472', '2119917', '1092405', '2063085', '1170997', '1190689', '1164933', '1194067', '1191200', '2120360', '1113218']);

        $monthlyReturn = $this->monthlyReturn($account['unit_prices'], $debug, $account['acc_no']);

        if ($debug) {
            $this->logger->info(sprintf(
                "LeaderEquityStatsCalculator_2: Ranking points calculation: %s: monthly return: %.5f; remuneration fee: %.5f; risk coefficient: %.5f; active days: %.1f; max drawdown: %.5f; K: %.5f; Ranking points: %.5f",
                $account['acc_no'],
                $monthlyReturn,
                $account['remun_fee'],
                $riskCoef,
                $activeDays,
                $account['max_drawdown'],
                $k,
                ($monthlyReturn * $k)
            ));
        }


        return round($monthlyReturn * $k, 2); // quickfix: monthly return shouldn't be saved
    }

    /**
     * @deprecated
     *
     * @param array $account
     * @return float|null
     */
    private function calculateRankPointsNew(array $account)
    {
        if (
            !$account["is_public"] ||
            $account['is_test'] ||
            $account['active_followers_count'] >= 2000
        ) {
            return null;
        }

        switch ($account['risk_level']) {
            case 1:
                $riskCoef = 1.5;
                break;
            case 2:
                $riskCoef = 1.25;
                break;
            case 3:
                $riskCoef = 1.0;
                break;
            case 4:
                $riskCoef = 0.75;
                break;
            case 5:
                $riskCoef = 0.5;
                break;
            default:
                $riskCoef = 0.5;
        }

        $activeDays = 0;
        $unitPrices = array_values($account['unit_prices']);
        if (count($unitPrices) >= 2) {
            for ($i = 1; $i < count($unitPrices); $i++) {
                if ($unitPrices[$i] !== $unitPrices[$i - 1]) {
                    $activeDays++;
                }
            }
        }

        $k  = (1 - $account['remun_fee'] / 100);
        $k *= $riskCoef;
        $k *= 1 + $activeDays / 2000;
        ;
        $k *= (1 - $account['max_drawdown'] / 400);

        $debug = in_array($account['acc_no'], ['1119472', '2119917', '1092405', '2063085', '1170997', '1190689', '1164933', '1194067', '1191200', '2120360', '1113218']);

        $monthlyReturn = $this->monthlyReturn($account['unit_prices'], $debug, $account['acc_no']);

        if ($debug) {
            $this->logger->info(sprintf(
                "LeaderEquityStatsCalculator: Ranking points calculation: %s: monthly return: %.5f; remuneration fee: %.5f; risk coefficient: %.5f; active days: %.1f; max drawdown: %.5f; K: %.5f; Ranking points: %.5f",
                $account['acc_no'],
                $monthlyReturn,
                $account['remun_fee'],
                $riskCoef,
                $activeDays,
                $account['max_drawdown'],
                $k,
                ($monthlyReturn * $k)
            ));
        }


        return round($monthlyReturn * $k, 2); // quickfix: monthly return shouldn't be saved
    }

    /**
     * @param array $account
     * @return float|null
     */
    private function calculatePopPoints(array $account)
    {
        if (!$account["is_public"] || $account['is_test']) {
            return null;
        }

        return round(
            ($account['old_followers'] + $account['old_funds'] / 10000) * 0.5 +
            ($account['new_followers'] + $account['new_funds'] / 10000),
            2
        );
    }

    /**
     * Truncate number (which is aggregate account equity) to sting (e.g. 104334.53 to "10K")
     *
     * @param float $result
     * @return string
     */
    private function calculateAUM(float $result): string
    {

        switch (true) {
            case ($result <= 0.0):
                return '0';

            case ($result < 10000.0):
                return '< 10K';

            case ($result < 1000000.0):
                return sprintf("%dK", intval(floor($result / 10000.0) * 10));

            default:
                return sprintf("%.1fM", $result / 1000000.0);
        }
    }

    /**
     * @param array $unitPrices
     * @return bool
     */
    private function calculateIsVeteran(array $unitPrices)
    {
        $dt = DateTime::NOW()->relativeDatetime('-6 months');

        reset($unitPrices);
        $startDateTime = DateTime::of(key($unitPrices));

        return $startDateTime <= $dt;
    }

    /**
     * @param array $account
     * @return string
     */
    private function getManagerName(array $account)
    {
        $profile = $account['profile'];

        return $profile['show_name']
            ? ($profile['use_nickname'] ? $profile['nickname'] : $profile['fullname'])
            : '';
    }

    /**
     * @param array $account
     * @return string
     */
    private function getManagerCountry(array $account)
    {
        $profile = $account['profile'];

        return $profile['show_country']
            ? $profile['country']
            : '';
    }

    /**
     * @param array $account
     * @return string
     */
    private function getManagerAvatar(array $account)
    {
        $profile = $account['profile'];

        return $profile['avatar']
            ? "/static/ct/profiles/{$profile['leader_id']}_{$profile['avatar']}.jpeg"
            : '';
    }

    /**
     * @param $equity
     * @return float
     */
    private function calculateMinDeposit($equity, $safety_mode = false)
    {
        $minEquity = intval($this->minDeposit);
        $equity = intval($equity);

        // Check if someone set $minEquity to zero and prevent "division by zero" issue.
        if ($minEquity == 0) {
            return 0;
        }

        $requiredEquity = $equity * 1.2 / $minEquity;
        $result = ceil($requiredEquity / 10) * 10;

        if ($safety_mode) {
            $result = $result * 2;
        }

        return $result > $minEquity ? $result : $minEquity;
    }
}

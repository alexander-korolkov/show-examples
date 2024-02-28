<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics;

use Fxtm\CopyTrading\Application\Services\LeaderProfile\LeaderProfileChecker;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Model\Company\Company;

class LeaderTopSuitabilityService
{
    /**
     * @var LeaderProfileChecker
     */
    private $profileChecker;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * LeaderTopSuitabilityService constructor.
     * @param LeaderProfileChecker $profileChecker
     * @param SettingsRegistry $settingsRegistry
     */
    public function __construct(LeaderProfileChecker $profileChecker, SettingsRegistry $settingsRegistry)
    {
        $this->profileChecker = $profileChecker;
        $this->settingsRegistry = $settingsRegistry;
    }

    /**
     * Returns array of checkpoints for getting to the managers' top
     *
     * @param array $account
     * @return array
     */
    public function getTopCheckpoints(array $account) : array
    {
        $globalRatingBrokers = $this->settingsRegistry->get('global_rating.brokers');
        $globalRatingBrokers = explode(',', $globalRatingBrokers);

        if (in_array($account['broker'], $globalRatingBrokers)) {
            $broker = 'global';
        } else {
            $broker = $account['broker'];
        }

        $checkpoints = [];

        if ($this->settingsRegistry->get($broker . '.top_checkpoints.positive_return_required')) {
            $checkpoints['profit'] = $this->hasPositiveProfit($account);
        }

        $checkpoints['current_equity'] = $this->hasEnoughCurrentEquity(
            $account,
            $this->settingsRegistry->get($broker . '.top_checkpoints.current_equity', 0)
        );

        $checkpoints['average_equity'] = $this->hasEnoughAverageEquity(
            $account,
            $this->settingsRegistry->get($broker . '.top_checkpoints.average_equity', 0)
        );

        $checkpoints['trading_days_count'] = $this->hasEnoughTradingDays(
            $account,
            $this->settingsRegistry->get($broker . '.top_checkpoints.trading_days', 0)
        );

        if ($this->settingsRegistry->get($broker . '.top_checkpoints.trading_last_five_days_required')) {
            $checkpoints['last_time_opened_orders_count'] = $this->hasOrdersInLastFiveDays($account);
        }

        $checkpoints['closed_orders_count'] = $this->hasEnoughClosedOrders(
            $account,
            $this->settingsRegistry->get($broker . '.top_checkpoints.closed_positions', 0)
        );

        if ($this->settingsRegistry->get($broker . '.top_checkpoints.approved_description_required')) {
            $checkpoints['approved_description'] = $this->hasApprovedDescription($account);
        }

        if ($this->settingsRegistry->get($broker . '.top_checkpoints.active_profile_required')) {
            $checkpoints['active_profile'] = $this->hasActiveManagerProfile($account);
        }

        $checkpoints['leverage'] = $this->hasAvailableLeverage(
            $account,
            $this->settingsRegistry->get($broker . '.top_checkpoints.max_leverage', PHP_INT_MAX)
        );

        return $checkpoints;
    }

    /**
     * Method checks that given account
     * is suitable for strategy managers top
     *
     * @param array $account
     * @return bool
     */
    public function isSuitableForTop(array $account) : bool
    {
        $company = new Company($account['profile']['company_id']);
        if ($company->isEu()) {
            return false;
        }

        $checkpoints = $this->getTopCheckpoints($account);
        foreach ($checkpoints as $checkpoint) {
            if ($checkpoint['passed'] !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * profit > 0
     *
     * @param array $account
     * @return array
     */
    private function hasPositiveProfit(array $account) : array
    {
        return [
            'value' => $account["profit"] ?: 0,
            'threshold' => 0,
            'passed' => $account["profit"] > 0,
        ];
    }

    /**
     * profit_days + loss_days >= $count
     *
     * @param array $account
     * @param int $count
     * @return array
     */
    private function hasEnoughTradingDays(array $account, int $count) : array
    {
        return [
            'value' => ($account["profit_days"] + $account["loss_days"]) ?: 0,
            'threshold' => $count,
            'passed' => $account["profit_days"] + $account["loss_days"] >= $count,
        ];
    }

    /**
     * equity >= 500
     *
     * @param array $account
     * @param float $enoughEquity
     * @return array
     */
    private function hasEnoughCurrentEquity(array $account, float $enoughEquity) : array
    {
        return [
            'value' => $account['equity'] ? round($account['equity']) : 0,
            'threshold' => $enoughEquity,
            'passed' => $account['equity'] >= $enoughEquity,
        ];
    }

    /**
     * average equity >= 500
     *
     * @param array $account
     * @param float $enoughEquity
     * @return array
     */
    private function hasEnoughAverageEquity(array $account, float $enoughEquity) : array
    {
        return [
            'value' => $account['avg_equity'] ? round($account['avg_equity']) : 0,
            'threshold' => $enoughEquity,
            'passed' => $account['avg_equity'] >= $enoughEquity,
        ];
    }

    /**
     * date_time of last order >= current date_time - $days days
     *
     * @param array $account
     * @param int $days
     * @return array
     */
    private function hasOrdersInLastFiveDays(array $account) : array
    {
        return [
            'value' => $account['last_opened_orders_count_5'] ?: 0,
            'threshold' => 0,
            'passed' => $account['last_opened_orders_count_5'] > 0,
        ];
    }

    /**
     * closed orders count >= $count
     *
     * @param array $account
     * @param int $count
     * @return array
     */
    private function hasEnoughClosedOrders(array $account, int $count) : array
    {
        return [
            'value' => $account['closed_orders_count'] ?: 0,
            'threshold' => $count,
            'passed' => $account['closed_orders_count'] >= $count,
        ];
    }

    /**
     * account has approved description
     *
     * @param array $account
     * @return array
     */
    private function hasApprovedDescription(array $account) : array
    {
        return [
            'value' => null,
            'threshold' => null,
            'passed' => $this->profileChecker->checkFilledDescription($account['profile']),
        ];
    }

    /**
     * account has filled name and country, avatar and approved description
     *
     * @param array $account
     * @return array
     */
    private function hasActiveManagerProfile(array $account) : array
    {
        return [
            'value' => null,
            'threshold' => null,
            'passed' => $this->profileChecker->hasFilledProfile($account['profile']),
        ];
    }

    /**
     * leverage <= $leverage
     *
     * @param array $account
     * @param int $leverage
     * @return array
     */
    private function hasAvailableLeverage(array $account, int $leverage) : array
    {
        return [
            'value' => $account['leverage'] ?: 0,
            'threshold' => $leverage,
            'passed' => $account['leverage'] <= $leverage,
        ];
    }
}

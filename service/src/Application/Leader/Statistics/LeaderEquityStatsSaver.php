<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics;

use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\FileStorageGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Interfaces\Persistence\Query\MultipleUpsert;
use PDO;

class LeaderEquityStatsSaver
{
    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var DataSourceFactory
     */
    private $dataSourceFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var FileStorageGateway
     */
    private $fileStorage;

    /**
     * @var array
     */
    private $options = [];

    /**
     * LeaderEquityStatsSaver constructor.
     * @param FileStorageGateway $fileStorage
     * @param DataSourceFactory $dataSourceFactory
     * @param Timer $timer
     * @param Logger $logger
     */
    public function __construct(
        FileStorageGateway $fileStorage,
        DataSourceFactory $dataSourceFactory,
        Timer $timer,
        Logger $logger
    ) {
        $this->timer = $timer;
        $this->dataSourceFactory = $dataSourceFactory;
        $this->logger = $logger;
        $this->fileStorage = $fileStorage;
    }

    /**
     * @param array $options
     */
    private function setOptions(array $options)
    {
        if (isset($options['step'])) {
            $options['step'] = array_fill_keys((array) $options['step'], true);
        }
        if (isset($options['skip'])) {
            $options['skip'] = array_fill_keys((array) $options['skip'], true);
        }

        $this->options = $options;
    }

    /**
     * Check options with given step which can be canceled
     *
     * @param string $step
     * @return bool
     */
    private function isStepShouldExecute(string $step)
    {
        $include = empty($this->options['step']) || isset($this->options['step'][$step]);
        $exclude = isset($this->options['skip'][$step]);

        return $include && !$exclude;
    }

    /**
     * Cleans temporary tables
     * Temporary tables are needed for 2 steps data saving
     *
     * @return void
     */
    public function cleanTemporaryTables()
    {

        $ctDbConnection = $this->dataSourceFactory->getCTConnection();
        $ctDbConnection->exec("TRUNCATE TABLE `leader_equity_stats_tmp`");
        $ctDbConnection->exec("TRUNCATE TABLE `unit_prices_tmp`");
        $ctDbConnection->exec("TRUNCATE TABLE `unit_prices_hourly_tmp`");

        foreach (Broker::listOfIndependent() as $broker) {
            $sasDbConnection = $this->dataSourceFactory->getSasConnection($broker);
            $sasDbConnection->exec("TRUNCATE TABLE `ct_leader_equity_stats_tmp`");
            $sasDbConnection->exec("TRUNCATE TABLE `ct_leader_profiles_tmp`");
            $sasDbConnection->exec("TRUNCATE TABLE `ct_unit_prices_tmp`");
            $sasDbConnection->exec("TRUNCATE TABLE `ct_unit_prices_hourly_tmp`");
        }
    }

    /**
     * Removes data of all not handled accounts and Moves changes from temporary tables to public tables
     *
     * @param array $accounts full list of handled accounts
     * @return void
     * @throws \Exception
     */
    public function moveTemporaryData(array $accounts)
    {

        $accNos = array_filter(array_column($accounts, 'acc_no'));
        $profileIds = array_filter(array_column(array_column($accounts, 'profile'), 'client_id'));

        $accNos = implode(',', $accNos);
        $profileIds = implode(',', $profileIds);

        $multipleTransactions = [];

        $ctDbConnection = $this->dataSourceFactory->getCTConnection();
        $ctDbConnection->beginTransaction();

        $multipleTransactions[] = $ctDbConnection;

        try {

            $ctDbConnection->exec("TRUNCATE TABLE `leader_equity_stats`");
            $ctDbConnection->exec("DELETE FROM `leader_ranks` WHERE `acc_no` NOT IN ({$accNos})");

            $ctDbConnection->exec("TRUNCATE TABLE `unit_prices`");
            $ctDbConnection->exec("TRUNCATE TABLE `unit_prices_hourly`");

            (new MultipleUpsert('leader_equity_stats', ['acc_no'], [
                'owner_id', 'acc_name', 'prev_acc_name', 'acc_curr', 'acc_descr', 'remun_fee', 'is_public', 'is_followable',
                'privacy_mode', 'pop_points', 'followers', 'age_in_days', 'max_drawdown', 'volatility', 'risk_level', 'risk_level_points',
                'rank_points', 'rank_points_new', 'profit', 'profit_1d', 'profit_1w', 'profit_1m', 'profit_3m', 'profit_6m',
                'trading_days', 'profit_days', 'loss_days', 'avg_day_profit', 'avg_day_loss', 'avg_day_rate',
                'leverage', 'min_deposit', 'min_deposit_in_safety_mode', 'swap_free', 'chart', 'top_chart', 'flags', 'avg_equity', 'manager_name', 'country', 'avatar',
                'is_veteran', 'total_funds', 'total_funds_raw', 'equity', 'show_trading_details',
            ]))->fromTemporaryTable($ctDbConnection, 'leader_equity_stats_tmp');

            (new MultipleUpsert('unit_prices', ['acc_no', 'date_time'], [
                'unit_price',
            ]))->fromTemporaryTable($ctDbConnection, 'unit_prices_tmp');

            (new MultipleUpsert('unit_prices_hourly', ['acc_no', 'date_time'], [
                'unit_price',
            ]))->fromTemporaryTable($ctDbConnection, 'unit_prices_hourly_tmp');

        }
        catch (\Exception $exception) {

            $this->rollback($multipleTransactions);

            throw $exception;
        }


        foreach (Broker::listOfIndependent() as $broker) {
            $sasDbConnection = $this->dataSourceFactory->getSasConnection($broker);
            $sasDbConnection->beginTransaction();
            $multipleTransactions[] = $sasDbConnection;

            try {

                $sasDbConnection->exec("TRUNCATE TABLE `ct_leader_equity_stats`");
                $sasDbConnection->exec("TRUNCATE TABLE `ct_unit_prices`");
                $sasDbConnection->exec("TRUNCATE TABLE `ct_unit_prices_hourly`");

                $sasDbConnection->exec("DELETE FROM `ct_leader_ranks` WHERE `acc_no` NOT IN ({$accNos})");

                if ($profileIds) {
                    $sasDbConnection->exec("DELETE FROM `ct_leader_profiles` WHERE `client_id` NOT IN ({$profileIds})");
                }


                (new MultipleUpsert('ct_leader_equity_stats', ['acc_no'], [
                    'owner_id', 'acc_name', 'prev_acc_name', 'acc_curr', 'acc_descr', 'remun_fee', 'is_public', 'is_followable',
                    'privacy_mode', 'pop_points', 'followers', 'age_in_days', 'max_drawdown', 'volatility', 'risk_level',
                    'risk_level_points', 'rank_points', 'profit', 'profit_1d', 'profit_1w', 'profit_1m', 'profit_3m', 'profit_6m',
                    'trading_days', 'profit_days', 'loss_days',
                    'avg_day_profit', 'avg_day_loss', 'avg_day_rate', 'min_deposit', 'min_deposit_in_safety_mode',
                    'leverage', 'swap_free', 'chart', 'top_chart',
                    'flags', 'total_funds', 'total_funds_raw', 'ts', 'is_veteran', 'equity', 'show_trading_details',
                ]))->fromTemporaryTable($sasDbConnection, 'ct_leader_equity_stats_tmp');

                (new MultipleUpsert('ct_leader_profiles', ['client_id'], [
                    'fullname', 'country', 'avatar'
                ]))->fromTemporaryTable($sasDbConnection, 'ct_leader_profiles_tmp');

                (new MultipleUpsert('ct_unit_prices', ['acc_no', 'date_time'], [
                    'unit_price',
                ]))->fromTemporaryTable($sasDbConnection, 'ct_unit_prices_tmp');

                (new MultipleUpsert('ct_unit_prices_hourly', ['acc_no', 'date_time'], [
                    'unit_price',
                ]))->fromTemporaryTable($sasDbConnection, 'ct_unit_prices_hourly_tmp');

            }
            catch (\Exception $exception) {

                $this->rollback($multipleTransactions);

                throw $exception;
            }

        }

        $this->commit($multipleTransactions);
    }

    /**
     * Helper method, rollbacks transactions on multiple connections
     *
     * @param PDO[] $connections
     */
    private function rollback(array $connections)
    {
        /** @var PDO $PDO */
        foreach ($connections as $PDO) {
            try {
                $PDO->rollBack();
            }
            catch (\Exception $exception) {
                $this->logger->error('LeaderEquityStatsSaver: rollback transaction failed. Error: ' . $exception->getMessage());
            }
        }
    }

    /**
     * Helper method, commits transactions on multiple connections
     *
     * @param PDO[] $connections
     */
    private function commit(array $connections)
    {
        /** @var PDO $PDO */
        foreach ($connections as $PDO) {
            try {
                $PDO->commit();
            }
            catch (\Exception $exception) {
                $this->logger->error('LeaderEquityStatsSaver: commit transaction failed. Error: ' . $exception->getMessage());
            }
        }
    }

    /**
     * Saves given accounts' stats
     *
     * @param array $accounts
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function save(array $accounts, array $options)
    {
        $this->setOptions($options);
        $this->timer->start();

        $this->savePublicStateAndMinDeposit($accounts);
        $this->timer->measure('saving_is_public_and_min_deposit');

        if ($this->isStepShouldExecute('saveCharts')) {
            $this->saveCharts($accounts);
            $this->timer->measure('saving_charts');
        }

        if ($this->isStepShouldExecute('saveToCtDb')) {
            $this->saveToCtDatabase($accounts);
            $this->timer->measure('saving_ct_db');
        }

        if ($this->isStepShouldExecute('saveToSas')) {
            $this->saveToSasDatabase($accounts);
            $this->timer->measure('saving_sas_db');
        }

        return $accounts;
    }

    /**
     * Saves is_public and hidden_reason of accounts in the db
     *
     * @param array $accounts
     * @throws \Exception
     */
    private function savePublicStateAndMinDeposit(array $accounts)
    {
        $data = [];
        foreach ($accounts as $account) {
            if ($account['privacy_mode_changed'] || $account['min_deposit_changed']) {
                $data[] = [
                    'acc_no' => $account['acc_no'],
                    'is_public' => $account['is_public'],
                    'is_followable' => $account['is_followable'],
                    'hidden_reason' => $account['hidden_reason'],
                    'min_deposit' => $account['min_deposit'],
                    'min_deposit_in_safety_mode' => $account['min_deposit_in_safety_mode'],
                ];
            }
        }

        if (count($data) <= 0) {
            return;
        }

        try {
            $stmt = new MultipleUpsert('leader_accounts', ['acc_no'], ['is_public', 'is_followable', 'hidden_reason', 'min_deposit', 'min_deposit_in_safety_mode']);
            $stmt->execute($this->dataSourceFactory->getCTConnection(), $data);
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving public state and minimal deposit failed. Error: ' . $exception->getMessage());
        }
    }

    /**
     * Saves png charts on the server
     *
     * @param array $accounts
     */
    private function saveCharts(array &$accounts)
    {
        $permDir = "charts";
        $tempDir = "charts/tmp";

        try {
            $this->fileStorage->mkdir($tempDir);

            foreach ($accounts as &$account) {
                $this->fileStorage->write("{$tempDir}/{$account["acc_no"]}.png", $account["chart_bin"]);
                $account["chart"] = "/static/ct/{$permDir}/{$account["acc_no"]}.png";
            }
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving charts failed. Error: ' . $exception->getMessage());
        }
    }

    /**
     * Method replace chart images with
     * new from tmp directory
     */
    public function moveChartsFromTmpDirectory()
    {
        $permDir = "charts";
        $tempDir = "charts/tmp";

        try {
            $this->fileStorage->rmfiles($permDir);
            $this->fileStorage->mvfiles($tempDir, $permDir);
            $this->fileStorage->rmdir($tempDir);
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: moving charts from tmp directory failed. Error: ' . $exception->getMessage());
        }
    }

    /**
     * Saving equity stats to copy-trading database
     *
     * @param array $accounts
     * @throws \Exception
     */
    private function saveToCtDatabase(array $accounts)
    {
        $savingEquityStatsStmt = new MultipleUpsert('leader_equity_stats_tmp', ['acc_no'], [
            'owner_id', 'acc_name', 'prev_acc_name', 'acc_curr', 'acc_descr', 'remun_fee', 'is_public', 'is_followable',
            'privacy_mode', 'pop_points', 'followers', 'age_in_days', 'max_drawdown', 'volatility', 'risk_level', 'risk_level_points',
            'rank_points', 'rank_points_new', 'profit', 'profit_1d', 'profit_1w', 'profit_1m', 'profit_3m', 'profit_6m',
            'trading_days', 'profit_days', 'loss_days', 'avg_day_profit', 'avg_day_loss', 'avg_day_rate',
            'leverage', 'min_deposit', 'min_deposit_in_safety_mode', 'swap_free', 'chart', 'flags', 'avg_equity', 'manager_name', 'country', 'avatar',
            'is_veteran', 'total_funds', 'total_funds_raw', 'equity', 'show_trading_details',
        ]);

        $accounts = array_map(function ($account) {
            $account['flags'] = implode(',', array_keys(array_filter($account['flags'])));
            $account['equity'] = $account['show_equity'] ? $account['equity'] : null;
            return $account;
        }, $accounts);

        try {
            $savingEquityStatsStmt->execute($this->dataSourceFactory->getCTConnection(), $accounts);
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving data to copy-trading database failed. Error: ' . $exception->getMessage());
        }

        $unitPrices = [];
        foreach ($accounts as $account) {
            foreach ($account['unit_prices'] as $dateTime => $unitPrice) {
                $unitPrices[] = [
                    'acc_no' => $account['acc_no'],
                    'date_time' => (string)(Datetime::of($dateTime)),
                    'unit_price' => $unitPrice,
                ];
            }
        }

        $unitPricesStmt = new MultipleUpsert('unit_prices_tmp', ['acc_no', 'date_time'], ['unit_price']);

        try {
            $unitPricesStmt->execute($this->dataSourceFactory->getCTConnection(), $unitPrices);
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving unit prices to copy-trading database failed. Error: ' . $exception->getMessage());
        }

        unset($unitPrices);

        $hourlyUnitPrices = [];
        foreach ($accounts as $account) {
            foreach ($account['unit_prices_hourly'] as $dateTime => $unitPrice) {
                $hourlyUnitPrices[] = [
                    'acc_no' => $account['acc_no'],
                    'date_time' => $dateTime,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        $hourlyUnitPricesStmt = new MultipleUpsert('unit_prices_hourly_tmp', ['acc_no', 'date_time'], ['unit_price']);

        try {
            $hourlyUnitPricesStmt->execute($this->dataSourceFactory->getCTConnection(), $hourlyUnitPrices);
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving hourly unit prices to copy-trading database failed. Error: ' . $exception->getMessage());
        }
    }

    /**
     * Saving changes in leader_equity_stats, leader_profiles and unit_prices
     * to SAS database
     *
     * @param array $accounts
     * @throws \Exception
     */
    private function saveToSasDatabase(array $accounts)
    {
        $savingEquityStatsStmt = new MultipleUpsert('ct_leader_equity_stats_tmp', ['acc_no'], [
            'owner_id', 'acc_name', 'prev_acc_name', 'acc_curr', 'acc_descr', 'remun_fee', 'is_public', 'is_followable',
            'privacy_mode', 'pop_points', 'followers', 'age_in_days', 'max_drawdown', 'volatility', 'risk_level',
            'risk_level_points', 'rank_points', 'profit', 'profit_1d', 'profit_1w', 'profit_1m', 'profit_3m', 'profit_6m',
            'trading_days', 'profit_days', 'loss_days',
            'avg_day_profit', 'avg_day_loss', 'avg_day_rate', 'min_deposit', 'min_deposit_in_safety_mode',
            'leverage', 'swap_free', 'chart',
            'flags', 'total_funds', 'total_funds_raw', 'ts', 'is_veteran', 'equity', 'show_trading_details',
        ]);

        $accounts = array_map(function ($account) {
            $account['flags'] = implode(',', array_keys(array_filter($account['flags'])));
            $account['equity'] = $account['show_equity'] ? $account['equity'] : null;
            return $account;
        }, $accounts);

        try {
            foreach (Broker::listOfIndependent() as $broker) {
                $sasDbConnection = $this->dataSourceFactory->getSasConnection($broker);
                $savingEquityStatsStmt->execute($sasDbConnection, $accounts, $broker);
            }
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving data to sas database failed. Error: ' . $exception->getMessage());
        }

        $savingProfilesStmt = new MultipleUpsert('ct_leader_profiles_tmp', ['client_id'], ['fullname', 'country', 'avatar']);

        $profiles = [];
        foreach ($accounts as $account) {
            if (empty($profile = $account['profile'])) {
                continue;
            }

            $profiles[] = [
                'client_id' => $profile['leader_id'],
                'fullname' => !$profile['show_name'] ? '' : ($profile['use_nickname'] ? $profile['nickname'] : $profile['fullname']),
                'country' => !$profile['show_country'] ? '' : $profile['country'],
                'avatar' => !$profile['avatar'] ? null : "/static/ct/profiles/{$profile['leader_id']}_{$profile['avatar']}.jpeg"
            ];
        }

        try {
            foreach (Broker::listOfIndependent() as $broker) {
                $sasDbConnection = $this->dataSourceFactory->getSasConnection($broker);
                $savingProfilesStmt->execute($sasDbConnection, $profiles, $broker);
            }
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving profiles to sas database failed. Error: ' . $exception->getMessage());
        }

        unset($profiles);

        $unitPrices = [];
        foreach ($accounts as $account) {
            foreach ($account['unit_prices'] as $dateTime => $unitPrice) {
                $unitPrices[] = [
                    'acc_no' => $account['acc_no'],
                    'date_time' => (string)(Datetime::of($dateTime)),
                    'unit_price' => $unitPrice,
                ];
            }
        }

        $unitPricesStmt = new MultipleUpsert('ct_unit_prices_tmp', ['acc_no', 'date_time'], ['unit_price']);

        try {
            foreach (Broker::listOfIndependent() as $broker) {
                $sasDbConnection = $this->dataSourceFactory->getSasConnection($broker);
                $unitPricesStmt->execute($sasDbConnection, $unitPrices, $broker);
            }
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving unit prices to sas database failed. Error: ' . $exception->getMessage());
        }

        unset($unitPrices);

        $hourlyUnitPrices = [];
        foreach ($accounts as $account) {
            foreach ($account['unit_prices_hourly'] as $dateTime => $unitPrice) {
                $hourlyUnitPrices[] = [
                    'acc_no' => $account['acc_no'],
                    'date_time' => $dateTime,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        $hourlyUnitPricesStmt = new MultipleUpsert('ct_unit_prices_hourly_tmp', ['acc_no', 'date_time'], ['unit_price']);

        try {
            foreach (Broker::listOfIndependent() as $broker) {
                $sasDbConnection = $this->dataSourceFactory->getSasConnection($broker);
                $hourlyUnitPricesStmt->execute($sasDbConnection, $hourlyUnitPrices, $broker);
            }
        } catch (\Exception $exception) {
            $this->logger->error('LeaderEquityStatsSaver: saving hourly unit prices to sas database failed. Error: ' . $exception->getMessage());
        }
    }

}

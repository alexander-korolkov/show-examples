<?php

use Fxtm\CopyTrading\Application\Common\Locker;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\FxtmVendor\AbstractClasses\MyCmsAdapter;
use Fxtm\CopyTrading\FxtmVendor\AccountStatus\AccountStatus;
use Fxtm\CopyTrading\FxtmVendor\TradeAccount\TradeAccountFactory;
use Fxtm\CopyTrading\FxtmVendor\Transfer\PaymentData;

error_reporting(E_ALL);
ini_set("display_errors", "On");

require_once __DIR__ . '/common.php';

$fmt = function ($str) {
    return 'close_eu_retail_aggregate_accounts: ' . $str;
};

$onlyAccount = null;
foreach ($_SERVER['argv'] as $k => $v) {
    if (0 === strpos($v, '-account=')) {
        $onlyAccount = str_replace('-account=', '', $v);
        break;
    }
}


/** @var $dbConn PDO connection to ct db */
global $dbConn;
global $container;

/** @var Locker $locker */
$locker = $container['locker'];

$processName = 'close_eu_retail_aggregate_accounts';
if (!$locker->lock($processName)) {
    $logf($fmt('Cannot lock ' . $processName . ': already acquired: %s'), $locker->getLockInfo($processName));
    exit(0);
}

$logf($fmt('process started.'));

$onlyAccountCondition = '';
if ($onlyAccount != null) {
    $onlyAccountCondition = " AND la.aggr_acc_no = {$onlyAccount} ";
}

$stmt = $dbConn->query("
            SELECT la.aggr_acc_no AS login, la.broker AS broker
            FROM leader_accounts la
            WHERE la.aggr_acc_no IS NOT NULL AND la.owner_id LIKE '5%'
            AND la.owner_id NOT IN (
                9400046,
                9400065,
                50019016,
                50292043,
                50299951,
                50301042,
                50302340,
                50303601,
                50303728,
                50303729,
                50321968,
                50328636,
                50337898,
                50340689,
                50346155,
                50347933,
                50355778,
                50358528,
                50358532,
                50358536,
                61202597,
                80000001,
                80037336,
                80043567,
                80044692
            )
        " . $onlyAccountCondition);

$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logf($fmt('found %d logins.'), count($accounts));

foreach ($accounts as $account) {
    $logf($fmt('login %s, broker - %s'), $account['login'], $account['broker']);
    try {
        MyCmsAdapter::setBroker($account['broker']);
        $tradeAccount = TradeAccountFactory::requireByLogin($account['login']);
        $balance = $tradeAccount->getBalance();

        if ($balance != 0) {
            $paymentData = new PaymentData();
            $paymentData->setAmount(-1 * $balance);
            $paymentData->setComment('migr_euretail');
            $paymentData = $tradeAccount->changeBalance($paymentData);
            if ($paymentData->getResult()) {
                $logf($fmt('balance %s updated: %.2f'), $account['login'], $balance);
            } else {
                $logf($fmt(sprintf(
                    'change balance operation failed. Error: %s. Account: %s. Amount: %.2f.',
                    $paymentData->getparam('error_message'),
                    $account['login'],
                    $balance
                )));
            }
        }

        $tradeAccount->setReadOnly(1);
        $tradeAccount->setParam("status_id", AccountStatus::STATUS_DISABLED);
        $tradeAccount->setClosedMtGroup();

        $platform = $tradeAccount->getPlatform();
        $platform->editUser($tradeAccount);

        $logf($fmt('closed.'));

    } catch (Throwable $e) {
        $logf($fmt(sprintf(
            'exception occurred. Message: %s. Account: %s. Trace: %s',
            $e->getMessage(),
            $account['login'],
            $e->getTraceAsString()
        )));
    }
}

$logf($fmt('process finished.'));

$locker->unlock($processName);

exit(0);

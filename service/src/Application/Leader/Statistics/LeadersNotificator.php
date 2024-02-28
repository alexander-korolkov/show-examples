<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics;

use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;

class LeadersNotificator
{
    /**
     * @var NotificationGateway
     */
    private $notificationGateway;

    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var DateTime
     */
    private $thresholdDayCount;

    /**
     * @var float
     */
    private $thresholdProfitLevel;

    /**
     * LeadersNotificator constructor.
     * @param NotificationGateway $notificationGateway
     * @param SettingsRegistry $settingsRegistry
     * @param Timer $timer
     * @param Logger $logger
     */
    public function __construct(
        NotificationGateway $notificationGateway,
        SettingsRegistry $settingsRegistry,
        Timer $timer,
        Logger $logger
    ) {
        $this->notificationGateway = $notificationGateway;
        $this->timer = $timer;
        $this->logger = $logger;

        $this->thresholdDayCount = $settingsRegistry->get("leader.hide_inactive_days_threshold", 30);
        $this->thresholdProfitLevel = $settingsRegistry->get("leader.hide_profit_threshold", -90.0);
    }

    /**
     * Method puts to client notifications queue
     * messages for leaders which account was made private or will be it soon
     *
     * @param array $accounts
     */
    public function sendNotifications(array $accounts)
    {
        $this->timer->start();

        foreach ($accounts as $account) {
            if (isset($account['notification'])) {
                try {
                    $this->sendAccountHiddenNotification($account);
                } catch (\Throwable $e) {
                    $this->logger->error(sprintf(
                        "LeaderEquityStatsNotificator: Exception '%s' with message '%s' in %s on line %d.",
                        get_class($e),
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ));
                }
            }
        }
        $this->timer->measure('sending_notifications_to_leaders');
    }

    /**
     * Sends notifications about account's inactivation
     *
     * @param array $account
     */
    private function sendAccountHiddenNotification(array $account)
    {
        $this->notificationGateway->notifyClient(
            new ClientId($account["owner_id"]),
            $account['broker'],
            $account['notification'],
            [
                "reason" => $account['hidden_reason'],
                "accNo" => $account["acc_no"],
                "accName" => $account["acc_name"],
                "accCurrency" => $account["acc_curr"],
                "urlAccName" => str_replace(" ", "~", $account["acc_name"]),
                "days" => $this->thresholdDayCount,
                "profit" => $this->thresholdProfitLevel,
            ]
        );
    }
}

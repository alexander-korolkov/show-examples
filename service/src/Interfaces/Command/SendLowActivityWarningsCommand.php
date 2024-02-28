<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendLowActivityWarningsCommand extends Command
{
    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var NotificationGateway
     */
    private $notificationGateway;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * SendLowActivityWarningCommand constructor.
     * @param SettingsRegistry $settingsRegistry
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param NotificationGateway $notificationGateway
     * @param LoggerInterface $logger
     */
    public function __construct(
        SettingsRegistry $settingsRegistry,
        LeaderAccountRepository $leaderAccountRepository,
        NotificationGateway $notificationGateway,
        LoggerInterface $logger
    ) {
        $this->settingsRegistry = $settingsRegistry;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->notificationGateway = $notificationGateway;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:low_activity_warnings:send')
            ->setDescription('Command to found leaders which was inactive for ($leaderInactivityThreshold - 7) days and to send warning emails to them.')
            ->setHelp('Command to found leaders which was inactive for ($leaderInactivityThreshold - 7) days and to send warning emails to them.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('SendLowActivityWarningsJob started.');

        $inactivityThreshold = intval($this->settingsRegistry->get('leader.hide_inactive_days_threshold', 30));
        $oneWeekWarningDay = $inactivityThreshold - 6;

        $accounts = $this->leaderAccountRepository->getAccountsWithoutTradingAfterDateInterval('-'.$oneWeekWarningDay.' days');

        $this->logger->info(sprintf('SendLowActivityWarningsJob: found %d managers.', count($accounts)));

        foreach ($accounts as $leader) {
            $this->notificationGateway->notifyClient(
                new ClientId($leader["owner_id"]),
                $leader['broker'],
                NotificationGateway::LEADER_ACC_INACTIVE_NOTICE,
                [
                    "accNo" => $leader["acc_no"],
                    "accName" => $leader["acc_name"],
                    "urlAccName" => str_replace(" ", "~", $leader["acc_name"]),
                    "days" => $oneWeekWarningDay,
                ]
            );
            $this->logger->info(sprintf('SendLowActivityWarningsJob: email to leader %s have sent.', $leader["acc_no"]));
        }

        return 0;
    }
}

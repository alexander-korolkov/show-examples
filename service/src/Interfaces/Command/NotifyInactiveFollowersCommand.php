<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyInactiveFollowersCommand extends Command
{
    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var NotificationGateway
     */
    private $notificationGateway;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * NotifyInactiveFollowersCommand constructor.
     * @param SettingsRegistry $settingsRegistry
     * @param FollowerAccountRepository $followerAccountRepository
     * @param NotificationGateway $notificationGateway
     * @param LoggerInterface $logger
     */
    public function __construct(
       SettingsRegistry $settingsRegistry,
       FollowerAccountRepository $followerAccountRepository,
       NotificationGateway $notificationGateway,
       LoggerInterface $logger
    ) {
        $this->settingsRegistry = $settingsRegistry;
        $this->followerAccountRepository = $followerAccountRepository;
        $this->notificationGateway = $notificationGateway;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:inactive_followers:notify')
            ->setDescription('Command for to found investors which was paused for ($followerInactivityThreshold - 5) days and to send warning emails to them.')
            ->setHelp('Command for to found investors which was paused for ($followerInactivityThreshold - 5) days and to send warning emails to them.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('NotifyInactiveFollowersJob started.');

        $inactivityThreshold = intval($this->settingsRegistry->get('follower.inactivity_threshold', 90));
        $followers = $this->followerAccountRepository->getPausedFollowers($inactivityThreshold - 5, null, false);
        $this->logger->info(sprintf('NotifyInactiveFollowersJob: found %d investors.', count($followers)));

        foreach ($followers as $follower) {
            $this->notificationGateway->notifyClient(
                $follower->ownerId(),
                $follower->broker(),
                NotificationGateway::FOLLOWER_ACC_INACTIVE_WARNING,
                [
                    'accNo' => $follower->number()->value(),
                    'inactivityThreshold' => $inactivityThreshold,
                ]
            );
            $this->logger->info(sprintf('NotifyInactiveFollowersJob: email to follower %s have sent.', $follower->number()->value()));
        }

        $this->logger->info('NotifyInactiveFollowersJob finished.');
        return 0;
    }
}

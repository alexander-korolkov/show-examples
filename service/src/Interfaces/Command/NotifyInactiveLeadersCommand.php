<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyInactiveLeadersCommand extends Command
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
     * NotifyInactiveLeadersCommand constructor.
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
        $this->setName('app:inactive_leaders:notify')
            ->setDescription('Command to found leaders without trading activity for ($leaderInactivityThreshold - 5) days and to send warning emails to them.')
            ->setHelp('Command to found leaders without trading activity for ($leaderInactivityThreshold - 5) days and to send warning emails to them.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('NotifyInactiveLeadersJob started.');

        $inactivityThreshold = intval($this->settingsRegistry->get('leader.inactivity_threshold', 90));
        $leaders85AndMore = $this->leaderAccountRepository->getNotActive($inactivityThreshold - 5, null);
        $leaders86AndMore = $this->leaderAccountRepository->getNotActive($inactivityThreshold - 4, null);

        $leaders = array_diff($leaders85AndMore, $leaders86AndMore);
        $this->logger->info(sprintf('NotifyInactiveLeadersJob: found %d managers.', count($leaders)));

        foreach ($leaders as $leader) {
            $this->notificationGateway->notifyClient(
                $leader->ownerId(),
                $leader->broker(),
                NotificationGateway::LEADER_ACC_INACTIVE_WARNING,
                [
                    'accNo' => $leader->number()->value(),
                    'inactivityThreshold' => $inactivityThreshold,
                ]
            );
            $this->logger->info(sprintf('NotifyInactiveLeadersJob: email to leader %s have sent.', $leader->number()->value()));
        }

        $this->logger->info('NotifyInactiveLeadersJob finished.');
        return 0;
    }
}

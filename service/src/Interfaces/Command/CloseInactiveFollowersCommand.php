<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CloseInactiveFollowersCommand extends Command
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * NotifyInactiveFollowersCommand constructor.
     * @param SettingsRegistry $settingsRegistry
     * @param FollowerAccountRepository $followerAccountRepository
     * @param WorkflowManager $workflowManager
     * @param LoggerInterface $logger
     */
    public function __construct(
       SettingsRegistry $settingsRegistry,
       FollowerAccountRepository $followerAccountRepository,
       WorkflowManager $workflowManager,
       LoggerInterface $logger
    ) {
        $this->settingsRegistry = $settingsRegistry;
        $this->followerAccountRepository = $followerAccountRepository;
        $this->workflowManager = $workflowManager;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:inactive_followers:close')
            ->setDescription('Command for to found investors which was paused for $followerInactivityThreshold days and to close them.')
            ->setHelp('Command for to found investors which was paused for $followerInactivityThreshold days and to close them.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('CloseInactiveFollowersJob started.');

        $inactivityThreshold = intval($this->settingsRegistry->get('follower.inactivity_threshold', 90));
        $followers = $this->followerAccountRepository->getPausedFollowers($inactivityThreshold, 1000);
        $this->logger->info(sprintf('CloseInactiveFollowersJob: found %d investors.', count($followers)));

        $delaySeconds = 10;
        foreach ($followers as $follower) {
            $CloseAccountWorkflow = $this->workflowManager->newWorkflow(
                CloseAccountWorkflow::TYPE,
                new ContextData([
                    "accNo" => $follower->number()->value(),
                    "reason" => CloseAccountWorkflow::REASON_LONG_INACTIVITY,
                    ContextData::KEY_BROKER => $follower->broker(),
                ])
            );
            $CloseAccountWorkflow->scheduleAt(DateTime::of("+$delaySeconds seconds"));
            $this->workflowManager->enqueueWorkflow($CloseAccountWorkflow);

            $this->logger->info(sprintf('CloseInactiveFollowersJob: workflow to close investor %s have created.', $follower->number()->value()));
            $delaySeconds += 10;
        }

        $this->logger->info('CloseInactiveFollowersJob finished.');
        return 0;
    }
}

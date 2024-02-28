<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Leader\DisconnectNotActiveLeaderWorkflow;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CloseInactiveLeadersCommand extends Command
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * CloseInactiveLeadersCommand constructor.
     * @param SettingsRegistry $settingsRegistry
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param WorkflowManager $workflowManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        SettingsRegistry $settingsRegistry,
        LeaderAccountRepository $leaderAccountRepository,
        WorkflowManager $workflowManager,
        LoggerInterface $logger
    ) {
        $this->settingsRegistry = $settingsRegistry;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->workflowManager = $workflowManager;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:inactive_leaders:close')
            ->setDescription('Command to found leaders without trading activity for $leadersInactivityThreshold days and to close them.')
            ->setHelp('Command to found leaders without trading activity for $leadersInactivityThreshold days and to close them.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger->info('CloseInactiveLeadersJob started.');

        $inactivityThreshold = intval($this->settingsRegistry->get('leader.inactivity_threshold', 90));
        $leaders = $this->leaderAccountRepository->getNotActive($inactivityThreshold, 100);
        $this->logger->info(sprintf('CloseInactiveLeadersJob: found %d managers.', count($leaders)));

        $delaySeconds = 10;
        foreach ($leaders as $leader) {
            $CloseAccountWorkflow = $this->workflowManager->newWorkflow(
                DisconnectNotActiveLeaderWorkflow::TYPE,
                new ContextData([
                    'accNo' => $leader->number()->value(),
                    ContextData::KEY_BROKER => $leader->broker(),
                    ContextData::REASON => DisconnectNotActiveLeaderWorkflow::REASON_NO_TRADING_ACTIVITY,
                ])
            );
            $CloseAccountWorkflow->scheduleAt(DateTime::of("+$delaySeconds seconds"));
            $this->workflowManager->enqueueWorkflow($CloseAccountWorkflow);

            $this->logger->info(sprintf('CloseInactiveLeadersJob: workflow to close leader %s have created.', $leader->number()->value()));
            $delaySeconds += 10;
            $this->logger->info(sprintf('CloseInactiveLeadersJob: %s', $leader->number()->value()));
        }

        $this->logger->info('CloseInactiveLeadersJob finished.');
        return 0;
    }
}

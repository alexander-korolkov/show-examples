<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Leader\DisableCopyingWorkflow;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class FixInconsistencyCommand extends Command
{

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LeaderAccountRepository $leaderAccountRepository
     */
    public function setLeaderAccountRepository(LeaderAccountRepository $leaderAccountRepository): void
    {
        $this->leaderAccountRepository = $leaderAccountRepository;
    }

    /**
     * @param WorkflowManager $workflowManager
     */
    public function setWorkflowManager(WorkflowManager $workflowManager): void
    {
        $this->workflowManager = $workflowManager;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:fix-inconsistencies')
            ->setDescription('Fixes inconsistencies between web and plugin databases')
            ->setHelp('Fixes inconsistencies between web and plugin databases')
            ->addOption('debug', null, InputOption::VALUE_OPTIONAL, 'Do not create workflows');
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $debugMode = !empty($input->getOption('debug'));

        $accounts = $this->leaderAccountRepository->findInconsistentAccounts();
        $this->logger->warning(sprintf('Inconsistent accounts fixer: Found %s accounts', count($accounts)));

        /** @var LeaderAccount $account */
        foreach ($accounts as $account) {
            $account = $account->number()->value();

            $context = new ContextData([
                'accNo' => $account,
                'forced' => true,
                ContextData::KEY_BROKER => $account->broker(),
            ]);

            if ($debugMode) {
                $output->writeln(sprintf(
                    "%s\t",
                    $account
                ));
            } else {
                $workflow = $this
                    ->workflowManager
                    ->newWorkflow(DisableCopyingWorkflow::TYPE, $context);

                if ($this->workflowManager->enqueueWorkflow($workflow)) {
                    $this->logger->info("Inconsistent accounts fixer: #{$account} - workflow {$workflow->id()} was created.");
                } else {
                    $this->logger->warning("Inconsistent accounts fixer: #{$account} - workflow creation failed.");
                }
            }
        }
    }
}

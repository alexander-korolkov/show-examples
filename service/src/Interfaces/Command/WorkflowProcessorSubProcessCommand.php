<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkflowProcessorSubProcessCommand extends Command
{

    /**
     * @var WorkflowRepository
     */
    private $workflowRepository;

    /**
     * @var WorkflowManager
     */
    private $workflowProcessor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param mixed $workflowRepository
     */
    public function setWorkflowRepository(WorkflowRepository $workflowRepository) : void
    {
        $this->workflowRepository = $workflowRepository;
    }

    /**
     * @param mixed $workflowProcessor
     */
    public function setWorkflowProcessor(WorkflowManager $workflowProcessor) : void
    {
        $this->workflowProcessor = $workflowProcessor;
    }

    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:workflow_run')
            ->setDescription('Calls to the daemon of workflow processing')
            ->addArgument("ids", InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'ID of specified workflow')
            ->setHelp("Executes specified workflow");
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $ids = $input->getArgument('ids');
        $this->logger->info(self::fmt("Processing workflows: %s", [implode(', ', $ids)]));
        foreach ($ids as $id) {
            $id = intval($id);
            $attempts = 8; // Several attempts to execute a workflow but only if it is in processing status
            while ($this->executeWorkflow($id)) {
                $attempts --;
                if($attempts < 1) {
                    break;
                }
                usleep(250000); // 1/4rd seconds; (8 attempts for only 2 seconds)
            }
        }
        return 0;
    }

    /**
     * Executes workflow with specified id and returns true if workflow is still in progress:
     *  If a child workflow was scheduled on later time, execution of parental workflow must not block others
     *
     * @param int $id workflow identifier
     * @return bool status code
     */
    public function executeWorkflow(int $id) : bool
    {
        $workflow = $this->workflowRepository->findById($id);

        if (empty($workflow)) {
            $this->logger->warning(self::fmt("No workflow found for id #{$id}"));
            throw new \LogicException("Impossible state, workflow #{$id} not found");
        }

        if (!$this->workflowProcessor->processWorkflow($workflow)) {
            // with some reason WorkflowProcessor can't execute right now
            $this->logger->warning(self::fmt("Workflow #{$id} was not processed"));
            return false;
        }

        $this->logger->info(self::fmt("Workflow #{$id} is executed successfully"));

        return !$workflow->isDone();
    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[WORKER %s]: %s", self::class, vsprintf($msg, $params));
    }

}
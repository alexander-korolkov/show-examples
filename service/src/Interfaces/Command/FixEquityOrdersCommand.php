<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;


class FixEquityOrdersCommand extends Command
{

    /**
     * @var WorkflowRepository
     */
    private $workflowRepository;

    /**
     * @var EquityService
     */
    private $equityService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param WorkflowRepository $workflowRepository
     */
    public function setWorkflowRepository(WorkflowRepository $workflowRepository): void
    {
        $this->workflowRepository = $workflowRepository;
    }

    /**
     * @param EquityService $equityService
     */
    public function setEquityService(EquityService $equityService): void
    {
        $this->equityService = $equityService;
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
        $this->setName('app:fix-equities-orders')
            ->setDescription('Fixes missing equities orders')
            ->setHelp('Fixes missing equities')
            ->addOption('from',null, InputOption::VALUE_REQUIRED, 'date from')
            ->addOption('to',null,InputOption::VALUE_REQUIRED, 'date to')
            ->addOption('debug',null,InputOption::VALUE_OPTIONAL, 'dont store orders');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $debugMode = !empty($input->getOption('debug'));
        $dateFrom = new \DateTime($input->getOption('from'));
        $dateTo = new \DateTime($input->getOption('to'));

        $workflows = $this->workflowRepository->findAllProceedInPeriod(
            $dateFrom,
            $dateTo,
            [
                'follower.process_deposit',
                'follower.process_withdrawal',
                'follower.close_account',
                'follower.process_pay_commission'
            ]
        );

        $this->logger->info(sprintf("FixEquityOrdersCommand: Found: %s workflows",  count($workflows)));

        /** @var AbstractWorkflow $workflow */
        foreach ($workflows as $workflow) {
            $context =$workflow->getContext();
            if(
                ($context->has('amount') || $context->has('in_out') || $context->has('fee')) &&
                $context->has('equity') &&
                $context->has('orderTime') &&
                ($context->has('depositOrder') || $context->has('withdrawalOrder'))
            ) {

                switch (true) {
                    case $context->has('amount'):
                        $amount = new Money($context->get('amount'), Currency::USD());
                        break;
                    case $context->has('in_out'):
                        $amount = new Money($context->get('in_out'), Currency::USD());
                        break;
                    case $context->has('fee'):
                        $amount = new Money($context->get('fee'), Currency::USD());
                        break;
                    default:
                        throw new \LogicException();
                }

                $equity = new Money($context->get('equity'), Currency::USD());
                $orderTime = new \DateTime($context->get('orderTime'));

                switch (true) {
                    case $context->has('depositOrder'):
                        $order = $context->get('depositOrder');
                        break;
                    case $context->has('withdrawalOrder'):
                        $order = $context->get('withdrawalOrder');
                        $amount = $amount->multiply(-1.0);
                        break;
                    default:
                        throw new \LogicException();
                }

                $this->logger->info(
                    sprintf(
                        'FixEquityOrdersCommand: processing id = %s account = %s amount = %s order = %s time = %s equity = %s',
                        $workflow->id(),
                        $workflow->getCorrelationId(),
                        $amount,
                        $order,
                        $orderTime->format('Y-m-d H:i:s'),
                        $equity
                    )
                );

                if(!$debugMode) {
                    $this->equityService->fixTransactionEquityChange(
                        new AccountNumber($workflow->getCorrelationId()),
                        $equity,
                        $amount,
                        $order,
                        $orderTime->format('Y-m-d H:i:s')
                    );
                }
                else {
                    $output->writeln(sprintf(
                        "Workflow id:\t%s\taccount:\t%s\ttype:\t%s\tamount:\t%s\torder:\t%s\ttime:\t%s\tequity:\t%s",
                        $workflow->id(),
                        $workflow->getCorrelationId(),
                        $workflow->type(),
                        $amount,
                        $order,
                        $orderTime->format('Y-m-d H:i:s'),
                        $equity
                    ));
                }
            }
        }

    }
}

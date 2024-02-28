<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class WorkflowFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * WorkflowFactory constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * @param string $type
     * @param ContextData|null $context
     *
     * @return AbstractWorkflow
     */
    public function createNewOfType(string $type, ContextData $context = null)
    {
        $type = 'workflow.' . $type;
        if (!$this->container->has($type)) {
            throw new \RuntimeException("Workflow factory for type '{$type}' not found.");
        }

        /** @var AbstractWorkflow $workflow */
        $workflow = $this->container->get($type);
        $workflow->setLogger($this->logger);

        if ($context != null) {
            $workflow->setContext($context);
            if ($context->has(ContextData::KEY_BROKER)) {
                $workflow->setWorkflowBroker($context->get(ContextData::KEY_BROKER));
            }
        }

        return $workflow;
    }
}

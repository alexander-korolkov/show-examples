<?php

namespace Fxtm\CopyTrading\Application\Services\Workflow;

use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Interfaces\Controller\ExitStatus;
use Fxtm\CopyTrading\Server\Generated\Api\WorkflowApiInterface;
use Fxtm\CopyTrading\Server\Generated\Model\ActionResult;
use Psr\Log\LoggerInterface as Logger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class WorkflowService implements WorkflowApiInterface
{
    use LoggerTrait;
    use SecurityTrait;

    /**
     * @var PluginGatewayManager
     */
    private $pluginGatewayManager;

    /**
     * @var WorkflowRepository
     */
    private $workflowRepository;

    public function __construct(
        PluginGatewayManager $pluginGatewayManager,
        WorkflowRepository $workflowRepository,
        Security $security,
        Logger $logger
    ) {
        $this->pluginGatewayManager = $pluginGatewayManager;
        $this->workflowRepository = $workflowRepository;
        $this->setSecurityHandler($security);
        $this->setLogger($logger);
    }

    /**
     * Sets authentication method jwt
     *
     * @param string $value Value of the jwt authentication method.
     *
     * @return void
     */
    public function setjwt($value) {}

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function workflowsWorkflowIdReloadPost($workflowId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN]);

            $workflow = $this->workflowRepository->findById($workflowId);

            if (is_null($workflow)) {
                $responseCode = Response::HTTP_NOT_FOUND;
                return null;
            }

            if ($workflow->getState() === WorkflowState::FAILED) {
                $childWorkflows = $this->getChildWorkflowsToReload($workflowId);
                $this->cancelWorkflows($childWorkflows);

                $this->cancelPluginMessagesOfActivitiesByWorkflow($workflow);
                $this->clearContextOfActivitiesByWorkflow($workflow);
                $this->untriedActivitiesByWorkflow($workflow);

                $workflow->resetContext();
                $workflow->untried();

                $this->workflowRepository->store($workflow);
            }

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = Response::HTTP_FORBIDDEN;
            return null;
        } catch (\Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param int $mainWorkflowId
     * @return AbstractWorkflow[]
     */
    private function getChildWorkflowsToReload(int $mainWorkflowId): array
    {
        $workflowsToReload = [];
        $parentIds = [$mainWorkflowId];

        do {
            $childWorkflows = $this->workflowRepository->findAllByParentIds($parentIds);
            if (count($childWorkflows) > 0) {
                $workflowsToReload = array_merge($workflowsToReload, $childWorkflows);
                $parentIds = array_map(function (AbstractWorkflow $workflow) {
                    return $workflow->id();
                }, $childWorkflows);
            }
        } while (count($childWorkflows) > 0);

        return $workflowsToReload;
    }

    /**
     * @param AbstractWorkflow $workflow
     * @return void
     */
    private function cancelPluginMessagesOfActivitiesByWorkflow(AbstractWorkflow $workflow): void
    {
        $messageIdsByServerId = [];

        $activities = $workflow->getActivities();
        foreach ($activities as $activity) {
            $context = $activity->getContext();

            if ($context->has(ContextData::KEY_PLUGIN_MESSAGE_ID)) {
                if (!$context->has(ContextData::KEY_PLUGIN_SERVER_ID)) {
                    $context->set('warning', 'Can not cancel the message, no found the server id into this context');
                    continue;
                }

                $messageIdsByServerId[$context->get(ContextData::KEY_PLUGIN_SERVER_ID)][] = $context->get(ContextData::KEY_PLUGIN_MESSAGE_ID);
            }
        }

        if (empty($messageIdsByServerId)) {
            return;
        }

        foreach ($messageIdsByServerId as $serverId => $messageIds) {
            $pluginGateway = $this->pluginGatewayManager->getForServer($serverId);
            $pluginGateway->multipleMessagesCancelled($messageIds);
        }
    }

    /**
     * @param AbstractWorkflow[] $workflows
     * @return void
     */
    private function cancelWorkflows(array $workflows): void
    {
        foreach ($workflows as $workflow) {
            $this->cancelActivitiesByWorkflow($workflow);

            $workflow->cancel();
        }
    }

    /**
     * @param AbstractWorkflow $workflow
     * @return void
     */
    private function clearContextOfActivitiesByWorkflow(AbstractWorkflow $workflow): void
    {
        $activities = $workflow->getActivities();
        foreach ($activities as $activity) {
            $activity->clearContext();
        }
    }

    /**
     * @param AbstractWorkflow $workflow
     * @return void
     */
    private function untriedActivitiesByWorkflow(AbstractWorkflow $workflow): void
    {
        $activities = $workflow->getActivities();
        foreach ($activities as $activity) {
            $activity->untried();
        }
    }

    /**
     * @param AbstractWorkflow $workflow
     * @return void
     */
    private function cancelActivitiesByWorkflow(AbstractWorkflow $workflow): void
    {
        $activities = $workflow->getActivities();
        foreach ($activities as $activity) {
            $activity->cancel();
        }
    }

    /**
     * @return string
     */
    public function getWorkerName(): string
    {
        return 'WEB[WORKFLOW]';
    }
}

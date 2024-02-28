<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class UpdateDescriptionWorkflow extends BaseWorkflow
{
    const TYPE = "leader.update_description";

    /**
     * @var WorkflowRepository
     */
    private $workflowRepo;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var NotificationGateway
     */
    private $notifGateway;

    /**
     * UpdateDescriptionWorkflow constructor.
     * @param WorkflowRepository $workflowRepo
     * @param LeaderAccountRepository $leadAccRepo
     * @param NotificationGateway $notifGateway
     */
    public function __construct(
        WorkflowRepository $workflowRepo,
        LeaderAccountRepository $leadAccRepo,
        NotificationGateway $notifGateway
    ) {
        $this->workflowRepo = $workflowRepo;
        $this->leadAccRepo = $leadAccRepo;
        $this->notifGateway = $notifGateway;

        parent::__construct(
            $this->activities([
                "rejectPrevious",
                "waitForApproval",
                "updateAccount",
                "notifyClient"
            ])
        );
    }

    protected function rejectPrevious(Activity $activity): void
    {
        /* @var $workflow BaseWorkflow */
        $previous = array_filter(
            $this->workflowRepo->findProceedingByCorrelationIdAndType($this->getAccountNumber()->value(), self::TYPE),
            function (BaseWorkflow $workflow) {
                return $workflow->id() !== $this->id();
            }
        );
        if (empty($previous)) {
            $activity->skip();
            return;
        }

        foreach ($previous as $workflow) {
            $workflow->reject();
            try {
                $this->workflowRepo->store($workflow);
            } catch (\Exception $e) {
                $this->logException($e);
                return;
            }
        }
        $activity->succeed();
    }

    protected function waitForApproval(Activity $activity): void
    {
        if (!$this->getContext()->has("isApproved")) {
            $this->scheduleAt(DateTime::of("+5 minutes"));
            $activity->keepTrying();
            return;
        }
        $activity->succeed();
    }

    protected function updateAccount(Activity $activity): void
    {
        if (!$this->getContext()->get("isApproved")) {
            $activity->skip();
            return;
        }
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $leadAcc->setDescription($this->getDescription());
        try {
            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function notifyClient(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());

        $ctx = $this->getContext();
        $ctx->set("accName", $leadAcc->name());
        $ctx->set("urlAccName", str_replace(" ", "~", $leadAcc->name()));

        $this->notifGateway->notifyClient(
            $leadAcc->ownerId(),
            $leadAcc->broker(),
            $ctx->get("isApproved") ? NotificationGateway::LEADER_ACC_DESCRIPTION_APPROVED : NotificationGateway::LEADER_ACC_DESCRIPTION_REJECTED,
            $ctx->toArray()
        );
        $activity->succeed();
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getDescription()
    {
        return $this->getContext()->get("accDescr");
    }

    public function getAccountRepository()
    {
        return $this->leadAccRepo;
    }

    /**
     * Method should return true if all necessary conditions for
     * creating of this workflow are met
     *
     * @return bool
     */
    public function canBeCreated()
    {
        return !empty($this->leadAccRepo->getLightAccount($this->getAccountNumber()));
    }
}

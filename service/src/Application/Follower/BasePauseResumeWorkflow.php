<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

abstract class BasePauseResumeWorkflow extends BaseParentalWorkflow
{
    protected const SKIP_STOP_COPYING_FLAG = 'skip_stop_copying';

    public const CTX_FIELD_PAUSED = "copyingPaused";
    public const CTX_FIELD_JUST_ACTIVATED = 'justActivated';

    /**
     * @var FollowerAccountRepository
     */
    protected $follAccRepo;

    /**
     * @var DateTime
     */
    protected $resumeCopyingSchedule = null;

    protected function stopCopying(Activity $activity): void
    {
        if (
            $this->getContext()->has(self::SKIP_STOP_COPYING_FLAG) &&
            $this->getContext()->get(self::SKIP_STOP_COPYING_FLAG) === true
        ) {
            $this->logDebug($activity, __FUNCTION__, 'skip because stop is not necessary');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get follower account');
        $follAcc = $this->follAccRepo->getLightAccountOrFail(new AccountNumber($this->getCorrelationId()));
        if (!$follAcc->isCopying()) {
            $this->logDebug($activity, __FUNCTION__, 'skip because copying is already on pause');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'FindCreateExecute child workflow');
        $this->getContext()->set(ContextData::KEY_BROKER, $follAcc->broker());
        $status = $this->findCreateExecute(
            $this->getCorrelationId(),
            PauseCopyingWorkflow::TYPE,
            function () {
                return $this->createChild(PauseCopyingWorkflow::TYPE, $this->getContext());
            }
        );

        $status->updateActivity($activity);

        if ($activity->isSucceeded()) {
            $this->getContext()->set(self::CTX_FIELD_PAUSED, 1);
            $this->logDebug($activity, __FUNCTION__, 'Copying is stopped');
        }
    }

    protected function startCopying(Activity $activity): void
    {
        if ($this->needSkipResume()) {
            $this->logDebug($activity, __FUNCTION__, 'skipped');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'FindCreateExecute child workflow');
        $status = $this->findCreateExecute(
            $this->getCorrelationId(),
            ResumeCopyingWorkflow::TYPE,
            function () {
                return $this->createChild(
                    ResumeCopyingWorkflow::TYPE,
                    $this->getContext(),
                    $this->resumeCopyingSchedule
                );
            }
        );

        $status->updateActivity($activity);
    }

    protected function needSkipResume(): bool
    {
        return
            !$this->getContext()->has(self::CTX_FIELD_PAUSED) &&
            !$this->getContext()->has(self::CTX_FIELD_JUST_ACTIVATED);
    }
}

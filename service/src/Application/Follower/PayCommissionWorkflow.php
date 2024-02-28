<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ActivityException;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\Commission;
use Fxtm\CopyTrading\Domain\Model\Follower\CommissionRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class PayCommissionWorkflow extends BaseWorkflow
{
    const TYPE = "follower.process_pay_commission";

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var CommissionRepository
     */
    private $commissionRepository;

    /**
     * @var TransactionGateway
     */
    private $transactionGateway;

    /**
     * PayCommissionWorkflow constructor.
     * @param FollowerAccountRepository $followerAccountRepository
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param CommissionRepository $commissionRepository
     * @param TransactionGateway $transactionGateway
     */
    public function __construct(
        FollowerAccountRepository $followerAccountRepository,
        LeaderAccountRepository $leaderAccountRepository,
        CommissionRepository $commissionRepository,
        TransactionGateway $transactionGateway
    ) {
        $this->followerAccountRepository = $followerAccountRepository;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->commissionRepository = $commissionRepository;
        $this->transactionGateway = $transactionGateway;

        parent::__construct(
            $this->activities([
                'settleCommission',
                'executeTransferBetweenFollowerAccountAndWallet',
                'changeEquity',
                'executeTransferBetweenFollowerAndLeaderInnerOneBroker',
                'executeTransferBetweenFollowerAndLeaderFromDifferentBrokers',
                'executeTransferBetweenFollowerAndLeaderFromDifferentBrokers2',
            ])
        );
    }

    /**
     * @param Activity $activity
     */
    protected function settleCommission(Activity $activity): void
    {
        $followerAccount = $this->followerAccountRepository->findOrFail($this->getAccountNumber());

        $prevFeeLevel = $followerAccount->settlingEquity()->amount();
        $prevEquity = $this->getContext()->get("prevEquity");
        $settlingFunds = new Money(
            $this->getContext()->getIfHas('settlingFunds'),
            Currency::forCode($this->getContext()->get('currency'))
        );
        $settlingType = $this->getSettlingActionType();
        $settlingEquity = $this->getContext()->getIfHas('settlingEquity');

        $fee = $followerAccount->settle($settlingFunds, $settlingType, $settlingEquity);

        $this->getContext()->set('prevEquity', $prevEquity);
        $this->getContext()->set('prevFeeLvl', $prevFeeLevel);
        $this->getContext()->set('fee', $fee);

        //Update Net here.
        $followerAccount->updateNet($this->getFunds());

        try {
            $this->followerAccountRepository->store($followerAccount);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        //TODO: Need to calculate difference between $prevEquity and $prevFeeLevel and check that this difference
        // is greater than 0.01 (1 cent).
        //if ($prevEquity > $prevFeeLevel && $fee == 0.00 && $followerAccount->payableFee() != 0) {
        //    throw new ActivityException("Commission expected to be greater than 0.00 ({$prevEquity} > {$prevFeeLevel}, {$fee})");
        //}

        $commissionType = $this->getContext()->get('commissionType');
        $commissionComment = $this->getContext()->getIfHas('commissionComment') ?? '';
        $commissionDate = $this->getContext()->getIfHas('commissionDate');

        $commission = new Commission(
            $commissionType,
            $this->getAccountNumber()->value(),
            $fee,
            $prevEquity,
            $prevFeeLevel,
            $this->id(),
            $commissionComment,
            $commissionDate
        );
        $commission->setBroker($this->getBroker());
        $this->commissionRepository->store($commission);

        $activity->succeed();
    }

    /**
     * First, funds should be transported from follower's account to his wallet
     * In case of withdrawal or closing account, this action already had done in parent workflows
     *
     * @param Activity $activity
     */
    protected function executeTransferBetweenFollowerAccountAndWallet(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('fee') <= 0) {
            $activity->skip();
            return;
        }

        if (!$this->getContext()->has('accountToWalletTransactionId')) {
            //create transaction
            $transactionId = $this->transactionGateway->createSynchronousTransaction(
                $this->getAccountNumber(),
                $this->getBroker(),
                $this->getFunds()->amount()
            );
            $this->getContext()->set('accountToWalletTransactionId', $transactionId);
        } else {
            $transactionId = $this->getContext()->get('accountToWalletTransactionId');
        }

        $commission = $this->commissionRepository->findByWorkflowId($this->id());
        $commission->setTransId($transactionId);
        $this->commissionRepository->store($commission);

        if (!$this->transactionGateway->transferWasExecuted($transactionId, $this->getBroker())) {
            //execute transaction
            try {
                //Save time when we start executeTransaction
                $this->getContext()->set("orderTime", DateTime::NOW()->format('Y-m-d H:i:s'));

                $result = $this->transactionGateway->executeTransaction(
                    $transactionId,
                    $this->getBroker(),
                    TransactionGateway::TK_WITHDRAWAL,
                    $this->getAccountNumber()
                );
                if($result->getStatus() != TransactionGatewayExecuteResult::STATUS_OK) {
                    throw new \Exception(
                        sprintf(
                            "TransferGateway::executeTransaction() have returned invalid status (expected %d but got %d)",
                            TransactionGatewayExecuteResult::STATUS_OK,
                            $result->getStatus()
                        )
                    );
                }

                $order = $result->getOrder();
                if($order < 0) {
                    throw new \Exception(
                        sprintf(
                            "TransferGateway::executeTransaction() have returned invalid order number %d",
                            $order
                        )
                    );
                }

                $this->getContext()->set('withdrawalOrder', $order);

            } catch (\Exception $e) {
                $this->getContext()->set("errorMessage", $e->getMessage());
                $activity->fail();
            }
        }

        $activity->succeed();
    }

    /**
     * @param Activity $activity
     */
    protected function changeEquity(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('fee') <= 0) {
            $activity->skip();
            return;
        }

        if ($this->getContext()->getIfHas('transFailed')) {
            $activity->skip();
            return;
        }

        try {
            $follAcc = $this->followerAccountRepository->getLightAccount($this->getAccountNumber());

            $order = $this->getContext()->getIfHas('withdrawalOrder');

            if (!$order) {
                $activity->skip();
                return;
            }

            $prevEquity = $this->getContext()->getIfHas("prevEquity");
            $prevEquityMoney =  new Money($prevEquity, $follAcc->currency());
            $equity = $prevEquityMoney->subtract($this->getFunds());

            $this->getContext()->set('equity', $equity->amount());

            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
            $activity->fail();
        }
    }

    /**
     * @param Activity $activity
     */
    public function executeTransferBetweenFollowerAndLeaderInnerOneBroker(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('fee') > 0 && $this->commissionInnerOneBroker()) {
            $follower = $this->followerAccountRepository->getLightAccountOrFail($this->getAccountNumber());

            try {
                if (!$this->getContext()->has('commissionTransactionId')) {
                    //create transaction
                    $transactionId = $this->transactionGateway->createInnerBrokerTransaction(
                        $this->getContext()->get('accountToWalletTransactionId'),
                        $follower->leaderAccountNumber()->value(),
                        $follower->broker(),
                        $this->getContext()->get('fee')
                    );
                    $this->getContext()->set('commissionTransactionId', $transactionId);
                } else {
                    $transactionId = $this->getContext()->get('commissionTransactionId');
                }

                if (!$this->transactionGateway->transferWasExecuted($transactionId, $this->getBroker())) {

                    //execute transaction
                    $result = $this->transactionGateway->executeTransaction(
                        $transactionId,
                        $this->getBroker(),
                        TransactionGateway::TK_BOTH,
                        $this->getAccountNumber()
                    );

                    if($result->getStatus() != TransactionGatewayExecuteResult::STATUS_OK) {
                        throw new \Exception(
                            sprintf(
                                "TransferGateway::executeTransaction() have returned invalid status (expected %d but got %d)",
                                TransactionGatewayExecuteResult::STATUS_OK,
                                $result->getStatus()
                            )
                        );
                    }

                }

                $activity->succeed();
            } catch (\Exception $e) {
                $this->getContext()->set("errorMessage", $e->getMessage());
                $activity->fail();
            }
        } else {
            $activity->skip();
        }
    }

    /**
     * @param Activity $activity
     */
    public function executeTransferBetweenFollowerAndLeaderFromDifferentBrokers(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('fee') > 0 && !$this->commissionInnerOneBroker()) {
            $followerAccount = $this->followerAccountRepository->getLightAccountOrFail($this->getAccountNumber());
            $leaderAccount = $this->leaderAccountRepository->getLightAccountOrFail($followerAccount->leaderAccountNumber());

            try {
                if (!$this->getContext()->has('commissionTransactionId')) {
                    //create transaction
                    $transactionId = $this->transactionGateway->createBetweenBrokersTransaction(
                        $followerAccount->broker(),
                        $leaderAccount->broker(),
                        $leaderAccount->ownerId(),
                        $this->getContext()->get('accountToWalletTransactionId'),
                        $this->getContext()->get('fee'),
                        $followerAccount->currency()
                    );
                    $this->getContext()->set('commissionTransactionId', $transactionId);
                } else {
                    $transactionId = $this->getContext()->get('commissionTransactionId');
                }

                if (!$this->transactionGateway->transferWasExecuted($transactionId, $this->getBroker())) {

                    //execute transaction
                    $result = $this->transactionGateway->executeTransaction(
                        $transactionId,
                        $this->getBroker(),
                        TransactionGateway::TK_BOTH,
                        $this->getAccountNumber()
                    );

                    if($result->getStatus() != TransactionGatewayExecuteResult::STATUS_OK) {
                        throw new \Exception(
                            sprintf(
                                "TransferGateway::executeTransaction() have returned invalid status (expected %d but got %d)",
                                TransactionGatewayExecuteResult::STATUS_OK,
                                $result->getStatus()
                            )
                        );
                    }

                }

                $activity->succeed();
            } catch (\Exception $e) {
                $this->getContext()->set("errorMessage", $e->getMessage());
                $activity->fail();
            }
        } else {
            $activity->skip();
        }
    }

    /**
     * @param Activity $activity
     */
    public function executeTransferBetweenFollowerAndLeaderFromDifferentBrokers2(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('fee') > 0 && !$this->commissionInnerOneBroker()) {
            $firstTransferId = $this->getContext()->get('commissionTransactionId');
            if (!$this->transactionGateway->transferWasExecuted($firstTransferId, $this->getBroker())) {
                $this->scheduleAt(DateTime::of("+3 minutes"));
                $activity->keepTrying();
                return;
            }

            $followerAccount = $this->followerAccountRepository->getLightAccountOrFail($this->getAccountNumber());
            $leaderAccount = $this->leaderAccountRepository->getLightAccountOrFail($followerAccount->leaderAccountNumber());

            try {
                if (!$this->getContext()->has('commissionTransactionIdSecond')) {
                    //create transaction
                    $transactionId = $this->transactionGateway->createBetweenBrokersTransactionSecond(
                        $followerAccount->broker(),
                        $leaderAccount->broker(),
                        $leaderAccount->ownerId(),
                        $firstTransferId,
                        $this->getContext()->get('fee')
                    );
                    $this->getContext()->set('commissionTransactionIdSecond', $transactionId);
                } else {
                    $transactionId = $this->getContext()->get('commissionTransactionIdSecond');
                }

                if (!$this->transactionGateway->transferWasExecuted($transactionId, $leaderAccount->broker())) {

                    //execute transaction
                    $result = $this->transactionGateway->executeTransaction(
                        $transactionId,
                        $leaderAccount->broker(),
                        TransactionGateway::TK_BOTH,
                        $this->getAccountNumber()
                    );

                    if($result->getStatus() != TransactionGatewayExecuteResult::STATUS_OK) {
                        throw new \Exception(
                            sprintf(
                                "TransferGateway::executeTransaction() have returned invalid status (expected %d but got %d)",
                                TransactionGatewayExecuteResult::STATUS_OK,
                                $result->getStatus()
                            )
                        );
                    }

                }

                $activity->succeed();
            } catch (\Exception $e) {
                $this->getContext()->set("errorMessage", $e->getMessage());
                $activity->fail();
            }
        } else {
            $activity->skip();
        }
    }

    /**
     * @return bool
     */
    private function commissionInnerOneBroker()
    {
        $followerAccount = $this->followerAccountRepository->getLightAccountOrFail($this->getAccountNumber());
        $leaderAccount = $this->leaderAccountRepository->getLightAccountOrFail($followerAccount->leaderAccountNumber());

        return $followerAccount->broker() == $leaderAccount->broker();
    }

    /**
     * @return FollowerAccountRepository
     * @return LeaderAccountRepository
     */
    public function getAccountRepository()
    {
        return $this->followerAccountRepository;
    }

    /**
     * @return AccountNumber
     */
    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    /**
     * @return int
     */
    private function getSettlingActionType()
    {
        switch ($this->getContext()->get('commissionType')) {
            case Commission::TYPE_PERIODICAL:
                return FollowerAccount::SETTLING_ACTION_TYPE_CLOSE_PERIOD;
            case Commission::TYPE_WITHDRAWAL:
                return FollowerAccount::SETTLING_ACTION_TYPE_WITHDRAWAL;
            case Commission::TYPE_CLOSE_ACCOUNT:
                return FollowerAccount::SETTLING_ACTION_TYPE_CLOSE_ACCOUNT;
            default:
                throw new ActivityException('Unknown commission type!');
        }
    }

    /**
     * @return Money
     */
    private function getFunds()
    {
        return new Money(
            $this->getContext()->get('fee'),
            Currency::forCode($this->getContext()->get('currency'))
        );
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}

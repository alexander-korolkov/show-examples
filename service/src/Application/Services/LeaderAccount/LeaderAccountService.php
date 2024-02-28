<?php

namespace Fxtm\CopyTrading\Application\Services\LeaderAccount;

use Exception;
use Fxtm\CopyTrading\Application\Censorship\Censor;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Leader\ChangeShowEquityWorkflow;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Leader\AccountNameService;
use Fxtm\CopyTrading\Application\Leader\ChangeLeverageWorkflow;
use Fxtm\CopyTrading\Application\Leader\ChangePrivacyModeWorkflow;
use Fxtm\CopyTrading\Application\Leader\ChangeRemunerationFeeWorkflow;
use Fxtm\CopyTrading\Application\Leader\ChangeSwapFreeWorkflow;
use Fxtm\CopyTrading\Application\Leader\DeleteAccountWorkflow;
use Fxtm\CopyTrading\Application\Leader\DisableCopyingWorkflow;
use Fxtm\CopyTrading\Application\Leader\EnableCopyingWorkflow;
use Fxtm\CopyTrading\Application\Leader\RefreshAccountWorkflow;
use Fxtm\CopyTrading\Application\Leader\RegisterNewAccountWorkflow;
use Fxtm\CopyTrading\Application\Leader\UnlockAccountWorkflow;
use Fxtm\CopyTrading\Application\Leader\UpdateAccountNameWorkflow;
use Fxtm\CopyTrading\Application\Leader\UpdateDescriptionWorkflow;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Application\UniqueNameChecker\UniqueNameChecker;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\AccountDescriptionDeclinedByCensor;
use Fxtm\CopyTrading\Domain\Model\Leader\AccountNameAlreadyTaken;
use Fxtm\CopyTrading\Domain\Model\Leader\AccountNameAlreadyUpdated;
use Fxtm\CopyTrading\Domain\Model\Leader\AccountNameDeclinedByCensor;
use Fxtm\CopyTrading\Domain\Model\Leader\HiddenReason;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\RemittanceRestrictions;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\Controller\ExitStatus;
use Fxtm\CopyTrading\Interfaces\Repository\BrokerRepository;
use Fxtm\CopyTrading\Server\Generated\Api\LeaderAccountApiInterface;
use Fxtm\CopyTrading\Server\Generated\Model\ActionResult;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeCopyingModeRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeFeeRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeLeaderDescriptionRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeLeverageRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangePrivacyModeRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeShowEquityRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeSwapFreeRequest;
use Fxtm\CopyTrading\Server\Generated\Model\CreateAccountResponse;
use Fxtm\CopyTrading\Server\Generated\Model\CreateLeaderRequest;
use Fxtm\CopyTrading\Server\Generated\Model\CreateWorkflowResult;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorsByManagerNameResponse;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccount;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountCommissions;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountDailyStats;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountDailyStatsLastMonth;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountDescr;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountInvestors;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpoints;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsApprovedDescription;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsAverageEquity;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsClosedOrdersCount;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsCurrentEquity;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsLastTimeOpenedOrdersCount;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsLeverage;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsProfit;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderAccountTopCheckpointsTradingDaysCount;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderMonthlyStats;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderMonthlyStatsResult;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderNameRequest;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderReferrable;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderStatus;
use Fxtm\CopyTrading\Server\Generated\Model\RemittanceRestrictionsCheckRequest;
use Fxtm\CopyTrading\Server\Generated\Model\RemittanceRestrictionsCheckStatus;
use Fxtm\CopyTrading\Server\Generated\Model\ShortInvestorAccount;
use Fxtm\CopyTrading\Server\Generated\Model\UniqueNameResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class LeaderAccountService implements LeaderAccountApiInterface
{
    use LoggerTrait;
    use SecurityTrait;

    private const TOTAL_FUNDS_PERCENTAGE = 0.02;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * @var WorkflowRepository
     */
    private $workflowRepository;

    /**
     * @var BrokerRepository
     */
    private $brokerRepository;

    /**
     * @var Censor
     */
    private $censor;

    /**
     * @var UniqueNameChecker
     */
    private $uniqueNameChecker;

    /**
     * @var TradeOrderGatewayFacade
     */
    private $tradeOrderGateway;

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var AccountNameService
     */
    private $accountNameService;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;


    /**
     * LeaderAccountService constructor.
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepository
     * @param BrokerRepository $brokerRepository
     * @param Censor $censor
     * @param UniqueNameChecker $uniqueNameChecker
     * @param TradeOrderGatewayFacade $tradeOrderGateway
     * @param FollowerAccountRepository $followerAccountRepository
     * @param AccountNameService $accountNameService
     * @param Security $security
     * @param SettingsRegistry $settingsRegistry
     * @param Logger $logger
     * @param ClientGateway $clientGateway
     * @param TradeAccountGateway $tradeAccountGateway
     */
    public function __construct(
        LeaderAccountRepository $leaderAccountRepository,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepository,
        BrokerRepository $brokerRepository,
        Censor $censor,
        UniqueNameChecker $uniqueNameChecker,
        TradeOrderGatewayFacade $tradeOrderGateway,
        FollowerAccountRepository $followerAccountRepository,
        AccountNameService $accountNameService,
        Security $security,
        SettingsRegistry $settingsRegistry,
        Logger $logger,
        ClientGateway $clientGateway,
        TradeAccountGateway $tradeAccountGateway
    ) {
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->workflowManager = $workflowManager;
        $this->workflowRepository = $workflowRepository;
        $this->brokerRepository = $brokerRepository;
        $this->censor = $censor;
        $this->uniqueNameChecker = $uniqueNameChecker;
        $this->tradeOrderGateway = $tradeOrderGateway;
        $this->followerAccountRepository = $followerAccountRepository;
        $this->accountNameService = $accountNameService;
        $this->settingsRegistry = $settingsRegistry;
        $this->setSecurityHandler($security);
        $this->setLogger($logger);
        $this->clientGateway = $clientGateway;
        $this->tradeAccountGateway = $tradeAccountGateway;
    }


    /**
     * Sets authentication method jwt
     *
     * @param string $value Value of the jwt authentication method.
     *
     * @return void
     */
    public function setjwt($value)
    {
    }

    /**
     * Operation leadersAccountNumberClosePost
     *
     * Close leader's account
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberClosePost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $account = $this->leaderAccountRepository->getLightAccount(new AccountNumber($accountNumber));
            if (!$account->isOpen() || $this->workflowRepository->isPending(DeleteAccountWorkflow::TYPE, $accountNumber)) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::ACCOUNT_ALREADY_CLOSED,
                ]);
            }

            if ($this->tradeOrderGateway->hasOpenPositions($account)) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::LEADER_ACCOUNT_HAS_OPEN_POSITIONS,
                ]);
            }

            $broker = $this->brokerRepository->getByLeader($accountNumber);
            $workflow = $this->workflowManager->newWorkflow(
                DeleteAccountWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    ContextData::KEY_BROKER => $broker,
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberCopyingPatch
     *
     * Change copying mode of leader
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangeCopyingModeRequest $changeCopyingModeRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberCopyingPatch($accountNumber, ChangeCopyingModeRequest $changeCopyingModeRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $workflowType = $changeCopyingModeRequest->isCopying()
                ? EnableCopyingWorkflow::TYPE
                : DisableCopyingWorkflow::TYPE;

            $workflow = $this->workflowManager->newWorkflow(
                $workflowType,
                new ContextData([
                    'accNo' => $accountNumber,
                    ContextData::KEY_BROKER => $this->brokerRepository->getByLeader($accountNumber),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberDescriptionPatch
     *
     * Change leader account's description
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangeLeaderDescriptionRequest $changeLeaderDescriptionRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberDescriptionPatch($accountNumber, ChangeLeaderDescriptionRequest $changeLeaderDescriptionRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $this->ensureDescriptionPassedCensorship($changeLeaderDescriptionRequest->getDescription());

            $account = $this->leaderAccountRepository->getLightAccountOrFail(new AccountNumber($accountNumber));
            if ($account->isBlocked()) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::ACCOUNT_TEMPORARILY_BLOCKED,
                ]);
            }

            $proceeding = $this->workflowRepository->findProceedingByCorrelationIdAndType(
                $accountNumber,
                UpdateDescriptionWorkflow::TYPE
            );

            /* @var $workflow AbstractWorkflow */
            if (empty($workflow = array_shift($proceeding))) {
                $workflow = $this->workflowManager->newWorkflow(
                    UpdateDescriptionWorkflow::TYPE,
                    new ContextData([
                        'accNo' => $accountNumber,
                        'accDescr' => $changeLeaderDescriptionRequest->getDescription(),
                        ContextData::KEY_BROKER => $account->broker(),
                    ])
                );
            } else {
                $workflow->getContext()->set('accDescr', $changeLeaderDescriptionRequest->getDescription());
                $workflow->getContext()->remove('isApproved');
            }
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param string $description
     * @throws AccountDescriptionDeclinedByCensor
     */
    private function ensureDescriptionPassedCensorship($description)
    {
        if (!$this->censor->pass($description)) {
            throw new AccountDescriptionDeclinedByCensor($description);
        }
    }

    /**
     * Operation leadersAccountNumberFeePatch
     *
     * Change leader's remuneration fee
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangeFeeRequest $changeFeeRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberFeePatch($accountNumber, ChangeFeeRequest $changeFeeRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            if (is_null($changeFeeRequest->getFee()) || $changeFeeRequest->getFee() > 30 || $changeFeeRequest->getFee() < 0) {
                $responseCode = 400;
                return null;
            }

            $workflow = $this->workflowManager->newWorkflow(
                ChangeRemunerationFeeWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    'remunFee' => $changeFeeRequest->getFee(),
                    ContextData::KEY_BROKER => $this->brokerRepository->getByLeader($accountNumber),
                ])
            );
            $this->workflowManager->processWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $workflow->getResult(),
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberGet
     *
     * Get leader account
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LeaderAccount
     *
     * @throws Exception
     */
    public function leadersAccountNumberGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $data = $this->leaderAccountRepository->getArray($accountNumber);
            if (!$data) {
                $responseCode = 404;
                return null;
            }

            $lastMonthStats = array_map(function (array $item) {
                return new LeaderAccountDailyStatsLastMonth([
                    'date' => $item['date'],
                    'followers' => $item['followers'],
                    'funds' => $item['funds'],
                    'income' => $item['income'],
                ]);
            }, $data['daily_stats']['last_month']);
            $allTimeStats = array_map(function (array $item) {
                return new LeaderAccountDailyStatsLastMonth([
                    'date' => $item['date'],
                    'followers' => $item['followers'],
                    'funds' => $item['funds'],
                    'income' => $item['income'],
                ]);
            }, $data['daily_stats']['all_time']);

            $stats = new LeaderAccountDailyStats([
                'lastMonth' => $lastMonthStats,
                'allTime' => $allTimeStats,
            ]);

            $investors = array_map(function (array $investor) {
                return new LeaderAccountInvestors([
                    'accNo' => $investor['acc_no'],
                    'isCopying' => $investor['is_copying'],
                    'funds' => $investor['funds'],
                    'nextPayout' => $investor['next_payout'],
                    'payFee' => $investor['pay_fee'],
                    'profit' => $investor['profit'],
                ]);
            }, $data['investors']);

            $commissions = array_map(function (array $commission) {
                return new LeaderAccountCommissions([
                    'accNo' => $commission['acc_no'],
                    'date' => $commission['date'],
                    'amount' => $commission['amount'],
                    'reason' => $commission['reason'],
                    'comment' => $commission['comment'],
                ]);
            }, $data['commissions']);

            $topCheckpointsData = $data['top_checkpoints'];
            $topCheckpoints = new LeaderAccountTopCheckpoints([
                'profit' => new LeaderAccountTopCheckpointsProfit($topCheckpointsData['profit']),
                'currentEquity' => new LeaderAccountTopCheckpointsCurrentEquity($topCheckpointsData['current_equity']),
                'averageEquity' => new LeaderAccountTopCheckpointsAverageEquity($topCheckpointsData['average_equity']),
                'tradingDaysCount' => new LeaderAccountTopCheckpointsTradingDaysCount($topCheckpointsData['trading_days_count']),
                'lastTimeOpenedOrdersCount' => new LeaderAccountTopCheckpointsLastTimeOpenedOrdersCount($topCheckpointsData['last_time_opened_orders_count']),
                'closedOrdersCount' => new LeaderAccountTopCheckpointsClosedOrdersCount($topCheckpointsData['closed_orders_count']),
                'leverage' => new LeaderAccountTopCheckpointsLeverage($topCheckpointsData['leverage']),
                'approvedDescription' => isset($topCheckpointsData['approved_description'])
                    ? new LeaderAccountTopCheckpointsApprovedDescription($topCheckpointsData['approved_description'])
                    : null,
                'activeProfile' => isset($topCheckpointsData['active_profile'])
                    ? new LeaderAccountTopCheckpointsApprovedDescription($topCheckpointsData['active_profile'])
                    : null,
            ]);

            $descriptionText = '';
            $descriptionStatus = '';
             /* @var $workflow AbstractWorkflow */
            if (!empty($workflow = $this->workflowRepository->findLatestByCorrelationIdAndType($accountNumber, UpdateDescriptionWorkflow::TYPE))) {
                $ctx = $workflow->getContext();
                $isApproved = $ctx->getIfHas('isApproved');
                $descriptionText = $ctx->get('accDescr');
                $descriptionStatus = is_null($isApproved) ? 'under_review' : ($isApproved ? 'approved' : 'rejected');
            }

            $description = new LeaderAccountDescr([
                'text' => $descriptionText,
                'status' => $descriptionStatus,
            ]);

            return new LeaderAccount([
                'income' => $data['income'],
                'nextIncome' => $data['next_income'],
                'remunFee' => $data['remun_fee'],
                'accCurr' => $data['acc_curr'],
                'ownerId' => $data['owner_id'],
                'income1m' => $data['income_1m'],
                'income1w' => $data['income_1w'],
                'follToday' => $data['foll_today'],
                'dailyStats' => $stats,
                'volatility' => $data['volatility'],
                'nextPayout' => $data['next_payout'],
                'investors' => $investors,
                'pop' => $data['pop'],
                'incomeYd' => $data['income_yd'],
                'follNow' => $data['foll_now'],
                'commissions' => $commissions,
                'accNo' => $data['acc_no'],
                'isTest' => $data['is_test'],
                'isFollowable' => $data['is_followable'],
                'profit' => $data['profit'],
                'follFundsToday' => $data['foll_funds_today'],
                'isNameUpdated' => $data['is_name_updated'],
                'hiddenReason' => $data['hidden_reason'],
                'activatedAt' => $data['activated_at'],
                'openedAt' => $data['opened_at'],
                'accName' => $data['acc_name'],
                'descr' => $description,
                'follFundsNow' => $data['foll_funds_now'],
                'incomeTd' => $data['income_td'],
                'isPublic' => $data['is_public'],
                'status' => $data['status'],
                'topCheckpoints' => $topCheckpoints,
                'showEquity' => $data['show_equity'],
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberLeveragePatch
     *
     * Change leader's leverage
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangeLeverageRequest $changeLeverageRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberLeveragePatch($accountNumber, ChangeLeverageRequest $changeLeverageRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $broker = $this->brokerRepository->getByLeader($accountNumber);

            $workflow = $this->workflowManager->newWorkflow(
                ChangeLeverageWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    'leverage' => $changeLeverageRequest->getLeverage(),
                    ContextData::KEY_BROKER => $broker,
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberNamePatch
     *
     * Change leader account's name
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  LeaderNameRequest $leaderNameRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberNamePatch($accountNumber, LeaderNameRequest $leaderNameRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $this->ensureAccountNameNotUpdatedBefore($accountNumber);
            $this->ensureUniqueAccountName($leaderNameRequest->getName());
            $this->ensureAccountNamePassedCensorship($leaderNameRequest->getName());

            $workflow = $this->workflowManager->newWorkflow(
                UpdateAccountNameWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    'accName' => $leaderNameRequest->getName(),
                    ContextData::KEY_BROKER => $this->brokerRepository->getByLeader($accountNumber),
                ])
            );
            $this->workflowManager->processWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $workflow->getResult(),
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (AccountNameAlreadyUpdated $e) {
            return new CreateWorkflowResult([
                'status' => ExitStatus::LEADER_ACCOUNT_NAME_ALREADY_UPDATED,
                'result' => false,
            ]);
        } catch (AccountNameAlreadyTaken $e) {
            return new CreateWorkflowResult([
                'status' => ExitStatus::LEADER_NICKNAME_ALREADY_TAKEN,
                'result' => false,
            ]);
        } catch (AccountNameDeclinedByCensor $e) {
            return new CreateWorkflowResult([
                'status' => ExitStatus::LEADER_NICKNAME_DECLINED_BY_CENSOR,
                'result' => false,
            ]);
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param $accNo
     * @throws AccountNameAlreadyUpdated
     */
    private function ensureAccountNameNotUpdatedBefore($accNo)
    {
        if (!empty($this->workflowRepository->findByCorrelationIdAndType($accNo, UpdateAccountNameWorkflow::TYPE))) {
            throw new AccountNameAlreadyUpdated();
        }
    }

    /**
     * @param string $accName
     * @throws AccountNameAlreadyTaken
     */
    private function ensureUniqueAccountName($accName)
    {
        if (!$this->uniqueNameChecker->isSatisfiedBy($accName)) {
            throw new AccountNameAlreadyTaken($accName);
        }
    }

    /**
     * @param string $accName
     * @throws AccountNameDeclinedByCensor
     */
    private function ensureAccountNamePassedCensorship($accName)
    {
        if (!$this->censor->pass($accName)) {
            throw new AccountNameDeclinedByCensor($accName);
        }
    }

    /**
     * Operation leadersAccountNumberPrivacyModePatch
     *
     * Change leader's privacy mode
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangePrivacyModeRequest $changePrivacyModeRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberPrivacyModePatch($accountNumber, ChangePrivacyModeRequest $changePrivacyModeRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $broker = $this->brokerRepository->getByLeader($accountNumber);

            $workflow = $this->workflowManager->newWorkflow(
                ChangePrivacyModeWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    'privacyMode' => $changePrivacyModeRequest->getPrivacyMode(),
                    'closeFollowerAccounts' => $changePrivacyModeRequest->isCloseFollowers(),
                    ContextData::KEY_BROKER => $broker,
                ])
            );
            $this->workflowManager->processWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $workflow->getResult(),
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberRefreshPost
     *
     * Refresh leader's account
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberRefreshPost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN]);

            $workflow = $this->workflowManager->newWorkflow(
                RefreshAccountWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    ContextData::KEY_BROKER => $this->brokerRepository->getByLeader($accountNumber),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberShowEquityPatch
     *
     * Change leader's show_equity
     *
     * @param string $accountNumber  trade account&#39;s login (required)
     * @param ChangeShowEquityRequest $changeShowEquityRequest   (required)
     * @param int $responseCode     The HTTP response code to return
     * @param array $responseHeaders  Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     */
    public function leadersAccountNumberShowEquityPatch(
        $accountNumber,
        ChangeShowEquityRequest $changeShowEquityRequest,
        &$responseCode,
        array &$responseHeaders
    ) {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $workflow = $this->workflowManager->newWorkflow(
                ChangeShowEquityWorkflow::TYPE,
                new ContextData([
                    ContextData::KEY_ACC_NO => $accountNumber,
                    'show_equity' => $changeShowEquityRequest->isShowEquity(),
                    ContextData::KEY_BROKER => $this->brokerRepository->getByLeader($accountNumber),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberStatisticsMonthlyGet
     *
     * Leader monthly statistics by trades
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LeaderMonthlyStats
     *
     * @throws Exception
     */
    public function leadersAccountNumberStatisticsMonthlyGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            if (!$this->security->isGranted('ROLE_CLIENT')) {
                throw new AccessDeniedException();
            }

            $account = $this->leaderAccountRepository->getLightAccount(new AccountNumber($accountNumber));
            if (!$account) {
                $responseCode = 404;
                return null;
            }

            $stats = $this->leaderAccountRepository->getTradeMonthlyStats($accountNumber);

            return new LeaderMonthlyStats([
                'result' => array_map(function (array $result) {
                    return new LeaderMonthlyStatsResult($result);
                }, $stats),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberStatisticsStatusGet
     *
     * Leader status
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LeaderStatus
     *
     * @throws Exception
     */
    public function leadersAccountNumberStatisticsStatusGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            if (!$this->security->isGranted('ROLE_CLIENT')) {
                throw new AccessDeniedException();
            }

            $account = $this->leaderAccountRepository->getLightAccount(new AccountNumber($accountNumber));
            if (!$account) {
                $responseCode = 404;
                return null;
            }

            $accountNumber = new AccountNumber($accountNumber);
            return new LeaderStatus([
                'hasOpenOrders' => $this->tradeOrderGateway->getForAccount($account)->hasOpenPositions($accountNumber),
                'hasActiveInvestors' => $this->followerAccountRepository->getCountOfCopyingFollowerAccounts($accountNumber),
                'hasActivatedFollowers' => $this->followerAccountRepository->getCountOfCopyingFollowerAccounts($accountNumber),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberStopFollowersPost
     *
     * Stop leader's followers
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberStopFollowersPost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        throw new \Exception('Deprecated!');
    }

    /**
     * Operation leadersAccountNumberSwapFreePatch
     *
     * Change leader's swap free attribute
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangeSwapFreeRequest $changeSwapFreeRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberSwapFreePatch($accountNumber, ChangeSwapFreeRequest $changeSwapFreeRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $leaderAccount = $this->leaderAccountRepository->getLightAccountOrFail(new AccountNumber($accountNumber));

            if ($leaderAccount->hasOpenPositions()) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::LEADER_ACCOUNT_HAS_OPEN_POSITIONS,
                    'result' => false,
                ]);
            }

            $workflow = $this->workflowManager->newWorkflow(
                ChangeSwapFreeWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    'is_swap_free' => $changeSwapFreeRequest->isIsSwapFree(),
                    ContextData::KEY_BROKER => $changeSwapFreeRequest->getBroker(),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNumberUnlockPost
     *
     * Unlock leader's account
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function leadersAccountNumberUnlockPost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN]);

            $workflow = $this->workflowManager->newWorkflow(
                UnlockAccountWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    ContextData::KEY_BROKER => $this->brokerRepository->getByLeader($accountNumber),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersClientIdBrokerUniqueNameGet
     *
     * Get unique name
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  string $broker broker of client, fxtm or alpari (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return UniqueNameResponse
     *
     * @throws Exception
     */
    public function leadersClientIdBrokerUniqueNameGet($clientId, $broker, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            return new UniqueNameResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => $this->accountNameService->generateUniqueNameForClient(new ClientId($clientId), $broker),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersClientIdReferrableGet
     *
     * Get referrable by owner
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LeaderReferrable[]
     *
     * @throws Exception
     */
    public function leadersClientIdReferrableGet($clientId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN]);

            $results = $this->leaderAccountRepository->getReferrable($clientId);

            return array_map(function (array $result) {
                return new LeaderReferrable([
                    'leadAccNo' => $result['lead_acc_no'],
                    'leadAccName' => $result['lead_acc_name'],
                    'leadAccProfit' => $result['lead_acc_profit']
                ]);
            }, $results);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersIsUniqueNamePost
     *
     * Check name is unique
     *
     * @param  LeaderNameRequest $leaderNameRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersIsUniqueNamePost(LeaderNameRequest $leaderNameRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            return new ActionResult([
                'result' => $this->uniqueNameChecker->isSatisfiedBy($leaderNameRequest->getName()),
                'status' => ExitStatus::SUCCESS,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersPost
     *
     * Create leader account
     *
     * @param  CreateLeaderRequest $createLeaderRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateAccountResponse
     *
     * @throws Exception
     */
    public function leadersPost(CreateLeaderRequest $createLeaderRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $isAllowedAccountOpening = (bool) $this->settingsRegistry->get($this->settingsRegistry::ACCOUNTS_OPENING_PROCESSING_SETTING_NAME, 0);
            if (!$isAllowedAccountOpening) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::ACCOUNT_OPENING_IS_BLOCKED,
                    'result' => false,
                ]);
            }

            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $tradeAcc = $this->tradeAccountGateway->fetchAccountByNumber(
                new AccountNumber($createLeaderRequest->getAccountNumber()),
                $createLeaderRequest->getBroker()
            );
            $client = $this->clientGateway->fetchClientByClientId($tradeAcc->ownerId(), $createLeaderRequest->getBroker());
            if ($client->getCompany()->isEu()) {
                throw new Exception("Registration of new leaders accounts of EU clients is disabled");
            }

            if (!empty($createLeaderRequest->getAccountName())) {
                $this->ensureAccountNamePassedCensorship($createLeaderRequest->getAccountName());
                $this->ensureUniqueAccountName($createLeaderRequest->getAccountName());
            }

            $workflow = $this->workflowManager->newWorkflow(
                RegisterNewAccountWorkflow::TYPE,
                new ContextData([
                    'accNo' => $createLeaderRequest->getAccountNumber(),
                    'accName' => $createLeaderRequest->getAccountName(),
                    'remunFee' => $createLeaderRequest->getFee(),
                    ContextData::KEY_BROKER => $createLeaderRequest->getBroker(),
                ])
            );
            $this->workflowManager->processWorkflow($workflow);

            if ($createLeaderRequest->isPrivate()) {
                $account = $this->leaderAccountRepository->getLightAccount(new AccountNumber($createLeaderRequest->getAccountNumber()));
                if ($account != null) {
                    $account->makePrivate();
                    $account->setHiddenReason(HiddenReason::BY_CLIENT);
                    $this->leaderAccountRepository->store($account);
                }
            }

            return new CreateAccountResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => $createLeaderRequest->getAccountNumber(),
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (AccountNameAlreadyTaken $e) {
            return new CreateAccountResponse([
                'status' => ExitStatus::LEADER_NICKNAME_ALREADY_TAKEN,
                'result' => false,
            ]);
        } catch (AccountNameDeclinedByCensor $e) {
            return new CreateAccountResponse([
                'status' => ExitStatus::LEADER_NICKNAME_DECLINED_BY_CENSOR,
                'result' => false,
            ]);
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersAccountNameInvestorAccountsGet
     *
     * Get investor accounts list by concrete leader
     *
     * @param  string $accountName  trade account&#39;s name (required)
     * @param  int $limit   (optional)
     * @param  int $offset   (optional)
     * @param  integer $responseCode     The HTTP response code to return
     * @param  array   $responseHeaders  Additional HTTP headers to return with the response ()
     *
     * @return InvestorsByManagerNameResponse
     *
     * @throws Exception
     */
    public function leadersAccountNameInvestorAccountsGet($accountName, $limit = null, $offset = null, &$responseCode, array &$responseHeaders)
    {
        try {
            $this->assertRequesterRoles([Role::CLIENT]);
            $accountNumber = $this->leaderAccountRepository->getAccountNumberByName($accountName);

            $investors = $this->leaderAccountRepository->getInvestors(null, $accountName, $limit, $offset);

            $investorsList = [];
            foreach ($investors as $investor) {
                $investorsList[] = new ShortInvestorAccount([
                    'accountNumber' => $investor['acc_no'],
                    'status' => $investor['is_copying'],
                    'equity' => $investor['funds'],
                    'profit' => $investor['profit'],
                ]);
            }

            return new InvestorsByManagerNameResponse([
                'leaderAccountNumber' => $accountNumber,
                'investors' => $investorsList,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param string $accountNumber
     * @param RemittanceRestrictionsCheckRequest $request
     * @param int $responseCode
     * @param array $responseHeaders
     * @return RemittanceRestrictionsCheckStatus|null
     * @throws Exception
     */
    public function leadersAccountNumberRestrictionsCheckPost($accountNumber, RemittanceRestrictionsCheckRequest $request, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            if (!$this->security->isGranted('ROLE_CLIENT')) {
                throw new AccessDeniedException();
            }

            $account = $this->leaderAccountRepository->find(new AccountNumber($accountNumber));
            if (!$account) {
                return new RemittanceRestrictionsCheckStatus([
                    'allowed' => true,
                    'message' => null,
                    'code' => null
                ]);
            }

            $accountNumber = new AccountNumber($accountNumber);

            $hasOpenOrders = $this->tradeOrderGateway
                ->getForAccount($account)
                ->hasOpenPositions($accountNumber);

            $hasCopingInvestors = $this->followerAccountRepository
                ->getCountOfCopyingFollowerAccounts($accountNumber);

            if ($hasOpenOrders && $hasCopingInvestors) {
                if (
                    $request->getTransferTypeId() == RemittanceRestrictions::TRANSFER_TYPE_TRANSFER ||
                    $request->getTransferTypeId() == RemittanceRestrictions::TRANSFER_TYPE_WITHDRAW
                ) {
                    return new RemittanceRestrictionsCheckStatus([
                        'allowed' => false,
                        'message' => RemittanceRestrictions::RESTRICTION_OPEN_POSITIONS_MSG,
                        'code' => RemittanceRestrictions::RESTRICTION_OPEN_POSITIONS_CODE
                    ]);
                }
            }

            $hasActiveInvestors = $this->followerAccountRepository
                ->getCountOfActivatedFollowerAccounts($accountNumber);

            if (
                $request->getDirection() == RemittanceRestrictions::DIRECTION_FROM &&
                $request->getTransferTypeId() != RemittanceRestrictions::TRANSFER_TYPE_DEPOSIT &&
                $hasActiveInvestors
            ) {
                switch ($account->server()) {
                    case Server::ECN:
                        $min = $this->settingsRegistry->get('leader.min_equity.ecn');
                        break;
                    case Server::ADVANTAGE_ECN:
                        $min = $this->settingsRegistry->get('leader.min_equity.advantage_ecn');
                        break;
                    case Server::AI_ECN:
                        $min = $this->settingsRegistry->get('leader.min_equity.ai_ecn');
                        break;
                    case Server::ECN_ZERO:
                        $min = $this->settingsRegistry->get('leader.min_equity.ecn_zero');
                        break;
                    default:
                        $min = $this->settingsRegistry->get('leader_acc.min_equity');
                }
                $min = max(
                    $this->leaderAccountRepository->getTotalFundsForAccount($accountNumber) *
                    self::TOTAL_FUNDS_PERCENTAGE,
                    $min
                );

                if (($account->equity()->amount() - $request->getAmount()) < $min) {
                    return new RemittanceRestrictionsCheckStatus([
                        'allowed' => false,
                        'message' => RemittanceRestrictions::RESTRICTION_MINIMUM_EQUITY_MSG,
                        'code' => RemittanceRestrictions::RESTRICTION_MINIMUM_EQUITY_CODE,
                        'min' => $min
                    ]);
                }
            }

            return new RemittanceRestrictionsCheckStatus([
                'allowed' => true,
                'message' => null,
                'code' => null
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @return string
     */
    public function getWorkerName()
    {
        return 'WEB[LEADERS]';
    }
}

<?php

namespace Fxtm\CopyTrading\Application\Services\StrategyManager;

use Exception;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Application\Utils\FloatUtils;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\StrategyManager\StrategyManagerRepository;
use Fxtm\CopyTrading\Server\Generated\Api\StrategyManagerApiInterface;
use Fxtm\CopyTrading\Server\Generated\Model\ManagerTradingReview;
use Fxtm\CopyTrading\Server\Generated\Model\StrategyManager;
use Fxtm\CopyTrading\Server\Generated\Model\StrategyManagerStats;
use Fxtm\CopyTrading\Server\Generated\Model\StrategyManagerStatsSymbolReview;
use Fxtm\CopyTrading\Server\Generated\Model\StrategyManagerStatsSymbolReviewPairs;
use Fxtm\CopyTrading\Server\Generated\Model\StrategyManagerStatsSymbolReviewTotalsAndStats;
use Fxtm\CopyTrading\Server\Generated\Model\StrategyManagerStatsTradingReview;
use Fxtm\CopyTrading\Server\Generated\Model\StrategyManagerStatsUnitPrices;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class StrategyManagerService implements StrategyManagerApiInterface
{

    use LoggerTrait, SecurityTrait;

    /**
     * @var StrategyManagerRepository
     */
    private $strategyManagerRepository;

    /**
     * @var EquityService
     */
    private $equityService;

    /**
     * StrategyManagerService constructor.
     * @param StrategyManagerRepository $strategyManagerRepository
     * @param EquityService $equityService
     * @param Security $security
     * @param Logger $logger
     */
    public function __construct(
        StrategyManagerRepository $strategyManagerRepository,
        EquityService $equityService,
        Security $security,
        Logger $logger
    ) {
        $this->equityService = $equityService;
        $this->setSecurityHandler($security);
        $this->setLogger($logger);
        $this->strategyManagerRepository = $strategyManagerRepository;
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
     * Operation managerStatsAccountNumberGet
     *
     * Get concrete strategy manager account's data
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return StrategyManager
     *
     * @throws Exception
     */
    public function managerStatsAccountNumberGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $manager = $this->strategyManagerRepository->get($accountNumber);
            if (!$manager) {
                $responseCode = 404;
                return null;
            }

            return new StrategyManager($manager);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation managerStatsAccountNumberStatsGet
     *
     * Get trading review of concrete strategy manager
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  string $date date of needed statistics (optional)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return StrategyManagerStats
     *
     * @throws Exception
     */
    public function managerStatsAccountNumberStatsGet($accountNumber, $date = null, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $stats['unitPrices'] = $this->getUnitPrices($accountNumber);
            $stats['symbolReview'] = $this->getSymbolReview($accountNumber, $date);
            $stats['tradingReview'] = $this->getTradingReview($accountNumber, $date);

            return new StrategyManagerStats($stats);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param $accountNumber
     *
     * @return StrategyManagerStatsUnitPrices[]
     */
    private function getUnitPrices($accountNumber)
    {
        $unitPrices = $this->equityService->getAccountEquity(new AccountNumber($accountNumber));

        return array_map(function (array $unitPrice) {
            return new StrategyManagerStatsUnitPrices([
                'accNo' => $unitPrice['acc_no'],
                'dateTime' => $unitPrice['date_time'],
                'equity' => $unitPrice['equity'],
                'inOut' => $unitPrice['in_out'],
                'unitPrice' => $unitPrice['unit_price'],
            ]);
        }, $unitPrices);
    }

    /**
     * @param $accountNumber
     * @param string $date
     *
     * @return StrategyManagerStatsSymbolReview
     */
    private function getSymbolReview($accountNumber, $date = null)
    {
        $instruments = $this->strategyManagerRepository->getTradeInstrumentsStats($accountNumber, $date);
        $instruments = array_map(function (array $pair) {
            return new StrategyManagerStatsSymbolReviewPairs($pair);
        }, $instruments);

        $statsAndTotals = $this->strategyManagerRepository->getTradingReview($accountNumber, $date);

        $whole = $statsAndTotals['total_trades'];

        $percentage = function ($part) use ($whole) {
            bcscale(2);
            return $whole ? bcdiv(bcmul(
                FloatUtils::toString($part),
                '100'
            ),
                FloatUtils::toString($whole)
            ) : 0;
        };

        $statsAndTotals["totalBuyPerc"]         = $percentage($statsAndTotals["total_buy"]);
        $statsAndTotals["totalSellPerc"]        = $percentage($statsAndTotals["total_sell"]);
        $statsAndTotals["totalProfitPerc"]      = $percentage($statsAndTotals["total_profit"]);
        $statsAndTotals["totalLossPerc"]        = $percentage($statsAndTotals["total_loss"]);
        $statsAndTotals["totalStopLossPerc"]   = $percentage($statsAndTotals["total_stop_loss"]);
        $statsAndTotals["totalTakeProfitPerc"] = $percentage($statsAndTotals["total_take_profit"]);

        return new StrategyManagerStatsSymbolReview([
            'pairs' => $instruments,
            'totalsAndStats' => new StrategyManagerStatsSymbolReviewTotalsAndStats($statsAndTotals)
        ]);
    }

    /**
     * @param $accountNumber
     * @param string $date
     *
     * @return StrategyManagerStatsTradingReview
     */
    private function getTradingReview($accountNumber, $date = null)
    {
        $result =  [
            'buy' => new ManagerTradingReview($this->strategyManagerRepository->getTradingsPeriodReviewByType($accountNumber, 'buy', $date)),
            'sell' => new ManagerTradingReview($this->strategyManagerRepository->getTradingsPeriodReviewByType($accountNumber, 'sell', $date)),
            'profitable' => new ManagerTradingReview($this->strategyManagerRepository->getTradingsPeriodReviewByType($accountNumber, 'profit', $date)),
            'unprofitable' => new ManagerTradingReview($this->strategyManagerRepository->getTradingsPeriodReviewByType($accountNumber, 'loss', $date)),
        ];

        return new StrategyManagerStatsTradingReview($result);
    }

    /**
     * Operation managerStatsGet
     *
     * Returns list of strategy managers
     *
     * @param  bool $top show only top managers (optional)
     * @param  bool $euOnly show only managers for eu (optional)
     * @param  int $minInvestorsCount show only managers with investors\&quot; count bigger or equal than value (optional)
     * @param  int $minProfit show only managers with profit bigger or equal than value (optional)
     * @param  int $maxProfitShare show only managers with fee lower or equal than value (optional)
     * @param  int $maxDrawdown show only managers with drawdown lower or equal than value (optional)
     * @param  int $maxRiskLevel show only managers with risk level lower or equal than value (optional)
     * @param  int $minAge show only managers with age in months bigger or equal than value (optional)
     * @param  bool $veteran show only veteran managers (optional)
     * @param  string $country show only managers with country which contains the value (optional)
     * @param  string $name show only managers with account name which contains the value (optional)
     * @param  string $sortBy show filtered managers sorted by given name of field (optional)
     * @param  string $sortOrder ascendant or descendant sorting order (optional)
     * @param  float $limit (optional)
     * @param  float $offset (optional)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return StrategyManager[]
     *
     * @throws Exception
     */
    public function managerStatsGet($top = null, $euOnly = null, $minInvestorsCount = null, $minProfit = null, $maxProfitShare = null, $maxDrawdown = null, $maxRiskLevel = null, $minAge = null, $veteran = null, $country = null, $name = null, $sortBy = null, $sortOrder = null, $limit = null, $offset = null, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $results = $this->strategyManagerRepository->getAll($top, $euOnly, $minInvestorsCount, $minProfit, $maxProfitShare, $maxDrawdown, $maxRiskLevel, $minAge, $veteran, $country, $name, $sortBy, $sortOrder, $limit, $offset);

            return array_map(function (array $item) {
                return new StrategyManager($item);
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
     * @return string
     */
    public function getWorkerName()
    {
        return 'WEB[STRATEGY_MANAGERS]';
    }
}

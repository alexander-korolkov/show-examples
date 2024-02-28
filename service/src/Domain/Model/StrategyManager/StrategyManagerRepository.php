<?php

namespace Fxtm\CopyTrading\Domain\Model\StrategyManager;

interface StrategyManagerRepository
{
    /**
     * @param string $accountNumber
     * @return array
     */
    public function get($accountNumber);

    /**
     * @param string $accountNumber
     * @param string $date
     * @return array
     */
    public function getTradeInstrumentsStats($accountNumber, $date = null);

    /**
     * @param string $accountNumber
     * @param string $date
     * @return array
     */
    public function getTradingReview($accountNumber, $date = null);

    /**
     * @param string $accountNumber
     * @param string $type
     * @param string $date
     * @return array
\     */
    public function getTradingsPeriodReviewByType($accountNumber, $type, $date = null);

    /**
     * @param  bool $top  show only top managers (optional)
     * @param  bool $euOnly  show only managers for eu (optional)
     * @param  int $minInvestorsCount  show only managers with investors\&quot; count bigger or equal than value (optional)
     * @param  int $minProfit  show only managers with profit bigger or equal than value (optional)
     * @param  int $maxProfitShare  show only managers with fee lower or equal than value (optional)
     * @param  int $maxDrawdown  show only managers with drawdown lower or equal than value (optional)
     * @param  int $maxRiskLevel  show only managers with risk level lower or equal than value (optional)
     * @param  int $minAge  show only managers with age in months bigger or equal than value (optional)
     * @param  bool $veteran  show only veteran managers (optional)
     * @param  string $country  show only managers with country which contains the value (optional)
     * @param  string $name  show only managers with account name which contains the value (optional)
     * @param  string $sortBy  show filtered managers sorted by given name of field (optional)
     * @param  string $sortOrder  ascendant or descendant sorting order (optional)
     * @param  float $limit   (optional)
     * @param  float $offset   (optional)
     *
     * @return array
     *
     */
    public function getAll($top = null, $euOnly = null, $minInvestorsCount = null, $minProfit = null, $maxProfitShare = null, $maxDrawdown = null, $maxRiskLevel = null, $minAge = null, $veteran = null, $country = null, $name = null, $sortBy = null, $sortOrder = null, $limit = null, $offset = null);
}

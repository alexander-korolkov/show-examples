<?php

namespace Fxtm\CopyTrading\Domain\Model\News;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface NewsRepository
{
    public function store(News $news);

    /**
     * @param int $id
     * @return News
     */
    public function get($id);

    /**
     * @param int $id
     * @return News
     */
    public function find($id);

    /**
     * @param AccountNumber $accNo
     * @return News
     */
    public function findOneUnderReview(AccountNumber $accNo);

    /**
     * Returns all news
     * Filtered by given manager's account number or follower's client id
     *
     * @param string|null $accountNumber
     * @param string|null $clientId
     * @param bool|null $onlyApproved
     * @param string|null $rankType
     * @param bool|null $isPublic
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getAll(
        ?string $accountNumber = null,
        ?string $clientId = null,
        ?bool $onlyApproved = null,
        ?string $rankType = null,
        ?bool $isPublic = null,
        ?int $limit = null,
        ?int $offset = null
    );

    public function count(
        ?string $accountNumber = null,
        ?string $clientId = null,
        ?bool $onlyApproved = null,
        ?string $rankType = null,
        ?bool $isPublic = null
    );

    /**
     * Returns array of news data by given id
     *
     * @param string $id
     * @return array
     */
    public function getAsArray($id);
}

<?php

namespace Fxtm\CopyTrading\Domain\Model\QuestionnaireAttempt;

use Fxtm\CopyTrading\Domain\Model\Client\ClientId;

interface QuestionnaireAttemptRepository
{
    public function store(QuestionnaireAttempt $questAttempt);

    /**
     *
     * @param int $id
     * @return QuestionnaireAttempt
     */
    public function find($id);

    /**
     *
     * @return QuestionnaireAttempt
     */
    public function findByClientId(ClientId $clientId);

    /**
     * Returns last submitted questionnaire attempt for given client_id
     *
     * @param string $clientId
     * @return array|null
     */
    public function findLastAttempt(string $clientId);
}

<?php

namespace Fxtm\CopyTrading\Domain\Model\Questionnaire;

interface QuestionnaireRepository
{
    public function store(Questionnaire $questionnaire);

    /**
     *
     * @param int $id
     * @return Questionnaire
     */
    public function find($id);

    /**
     *
     * @return Questionnaire
     */
    public function findLatestPublished();
}

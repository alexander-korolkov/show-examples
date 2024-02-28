<?php

namespace Fxtm\CopyTrading\Domain\Model\QuestionnaireAttempt;

use Fxtm\CopyTrading\Domain\Common\DateTime;

class QuestionnaireAttempt
{
    private $id          = null;
    private $clientId    = null;
    private $questId     = null;
    private $submittedAt = null;
    private $answers     = [];
    private $points      = null;
    private $result      = null;

    public function __construct($clientId, $questId, array $answers, $points, $result)
    {
        $this->clientId    = $clientId;
        $this->questId     = $questId;
        $this->submittedAt = DateTime::NOW()->__toString();
        $this->answers     = $answers;
        $this->points      = $points;
        $this->result      = $result;
    }

    public function id()
    {
        return $this->id;
    }

    public function clientId()
    {
        return $this->clientId;
    }

    public function questionnaireId()
    {
        return $this->questId;
    }

    public function submittedAt()
    {
        return DateTime::of($this->submittedAt);
    }

    public function answers()
    {
        return $this->answers;
    }

    public function points()
    {
        return $this->points;
    }

    public function result()
    {
        return $this->result;
    }

    public function toArray()
    {
        return [
            "id"               => $this->id,
            "client_id"        => $this->clientId,
            "questionnaire_id" => $this->questId,
            "submitted_at"     => $this->submittedAt,
            "answers"          => $this->answers,
            "points"           => $this->points,
            "result"           => $this->result,
        ];
    }

    public function fromArray(array $array)
    {
        $this->id          = $array["id"];
        $this->clientId    = $array["client_id"];
        $this->questId     = $array["questionnaire_id"];
        $this->submittedAt = $array["submitted_at"];
        $this->answers     = $array["answers"];
        $this->points      = $array["points"];
        $this->result      = $array["result"];
    }

    public function __toString()
    {
        return print_r($this->toArray(), true);
    }
}

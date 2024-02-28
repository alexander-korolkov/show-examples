<?php

namespace Fxtm\CopyTrading\Domain\Model\Questionnaire;

use Fxtm\CopyTrading\Domain\Common\Arrays;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\DomainException;

class Questionnaire
{
    private $id          = null;
    private $status      = 0;
    private $createdAt   = null;
    private $publishedAt = null;
    private $questions   = [];

    public function __construct(array $questions)
    {
        $this->createdAt = DateTime::NOW()->__toString();
        array_walk($questions, function ($question) {
            $this->addQuestion($question);
        });
    }

    public function id()
    {
        return $this->id;
    }

    public function version()
    {
        return $this->id;
    }

    public function questions()
    {
        return $this->questions;
    }

    public function status()
    {
        return $this->status;
    }

    public function createdAt()
    {
        return DateTime::of($this->createdAt);
    }

    public function publishedAt()
    {
        return DateTime::of($this->createdAt);
    }

    public function publish()
    {
        $this->publishedAt = DateTime::NOW()->__toString();
        $this->status = 1;
    }

    private function addQuestion(Question $question)
    {
        $this->questions[$question->number()] = $question;
    }

    public function assess(array $answers)
    {
        if (sizeof($answers) < sizeof($this->questions)) {
            throw new DomainException(sprintf("Have %d questions but got only %d answers", sizeof($this->questions), sizeof($answers)));
        }

        return array_reduce(
            $this->questions,
            function ($points, $question) use ($answers) {
                $qNo  = $question->number();
                $chNo = $answers[$qNo];
                if (!isset($question->choices()[$chNo])) {
                    throw new DomainException(sprintf("Question #%d doesn't have choice #%d", $qNo, $chNo));
                }
                return $points += $question->choices()[$chNo]->points();
            },
            0
        );
    }

    public function toArray()
    {
        return [
            "id"           => $this->id,
            "status"       => $this->status,
            "created_at"   => $this->createdAt,
            "published_at" => $this->publishedAt,
            "questions"    => Arrays::toArrayOfArrays($this->questions)
        ];
    }

    public function fromArray(array $array)
    {
        $this->id          = $array["id"];
        $this->status      = $array["status"];
        $this->createdAt   = $array["created_at"];
        $this->publishedAt = $array["published_at"];
        $this->questions   = Arrays::fromArrayOfArrays($array["questions"], Question::CLASS);
    }
}

<?php

namespace Fxtm\CopyTrading\Domain\Model\Questionnaire;

class Choice
{
    private $questionId = null;
    private $no         = null;
    private $text       = "";
    private $points     = null;

    public function __construct($no, $text, $points)
    {
        $this->no     = $no;
        $this->text   = $text;
        $this->points = $points;
    }

    public function number()
    {
        return $this->no;
    }

    public function text()
    {
        return $this->points;
    }

    public function points()
    {
        return $this->points;
    }

    public function toArray()
    {
        return [
            "question_id" => $this->questionId,
            "no"          => $this->no,
            "text"        => $this->text,
            "points"      => $this->points
        ];
    }

    public function fromArray(array $array)
    {
        $this->questionId = $array["question_id"];
        $this->no         = $array["no"];
        $this->text       = $array["text"];
        $this->points     = $array["points"];
    }
}

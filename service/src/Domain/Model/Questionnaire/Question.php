<?php

namespace Fxtm\CopyTrading\Domain\Model\Questionnaire;

use Fxtm\CopyTrading\Domain\Common\Arrays;

class Question
{
    private $id       = null;
    private $no       = null;
    private $parentNo = null;
    private $text     = "";
    private $choices  = [];

    public function __construct($no, $text, array $choices, $parentNo = null)
    {
        $this->no       = $no;
        $this->parentNo = $parentNo;
        $this->text     = $text;
        array_walk($choices, function ($choice) {
            $this->addChoice($choice);
        });
    }

    public function number()
    {
        return $this->no;
    }

    public function parentNumber()
    {
        return $this->parentNo;
    }

    public function text()
    {
        return $this->text;
    }

    public function choices()
    {
        return $this->choices;
    }

    private function addChoice(Choice $choice)
    {
        $this->choices[$choice->number()] = $choice;
    }

    public function toArray()
    {
        return [
            "id"        => $this->id,
            "no"        => $this->no,
            "parent_no" => $this->parentNo,
            "text"      => $this->text,
            "choices"   => Arrays::toArrayOfArrays($this->choices)
        ];
    }

    public function fromArray(array $array)
    {
        $this->id       = $array["id"];
        $this->no       = $array["no"];
        $this->parentNo = $array["parent_no"];
        $this->text     = $array["text"];
        $this->choices  = Arrays::fromArrayOfArrays($array["choices"], Choice::CLASS);
    }
}

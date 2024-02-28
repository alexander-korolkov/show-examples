<?php

namespace Fxtm\CopyTrading\Domain\Model\News;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class News
{
    private $id          = null;
    private $accNo       = null;
    private $status      = NewsStatus::UNDER_REVIEW;
    private $title       = "";
    private $text        = "";
    private $submittedAt = null;
    private $updatedAt   = null;
    private $reviewedAt  = null;

    public function __construct(AccountNumber $accNo, $title, $text)
    {
        $this->accNo = $accNo->value();
        $this->title = $title;
        $this->text  = $text;

        $this->submittedAt = $this->updatedAt = DateTime::NOW()->__toString();
    }

    public function id()
    {
        return $this->id;
    }

    public function leaderAccountNumber()
    {
        return new AccountNumber($this->accNo);
    }

    public function title()
    {
        return $this->title;
    }

    public function text()
    {
        return $this->text;
    }

    public function status()
    {
        return $this->status;
    }

    public function isApproved()
    {
        return $this->status() == NewsStatus::APPROVED;
    }

    public function isUnderReview()
    {
        return $this->status() == NewsStatus::UNDER_REVIEW;
    }

    public function submittedAt()
    {
        return DateTime::of($this->submittedAt);
    }

    public function updatedAt()
    {
        return DateTime::of($this->updatedAt);
    }

    public function reviewedAt()
    {
        return empty($this->reviewedAt) ? null : DateTime::of($this->reviewedAt);
    }

    public function setTitle($title)
    {
        $this->title = $title;
        $this->updatedAt = DateTime::NOW()->__toString();
    }

    public function setText($text)
    {
        $this->text = $text;
        $this->updatedAt = DateTime::NOW()->__toString();
    }

    public function approve()
    {
        $this->status = NewsStatus::APPROVED;
        $this->reviewedAt = DateTime::NOW()->__toString();
    }

    public function reject()
    {
        $this->status = NewsStatus::REJECTED;
        $this->reviewedAt = DateTime::NOW()->__toString();
    }

    public function review()
    {
        $this->status = NewsStatus::UNDER_REVIEW;
        $this->reviewedAt = null;
    }

    public function toArray()
    {
        return array(
            "id"           => $this->id,
            "acc_no"       => $this->accNo,
            "title"        => $this->title,
            "text"         => $this->text,
            "status"       => $this->status,
            "submitted_at" => $this->submittedAt,
            "updated_at"   => $this->updatedAt,
            "reviewed_at"  => $this->reviewedAt,
        );
    }

    public function fromArray(array $array)
    {
        $this->id          = $array["id"];
        $this->accNo       = $array["acc_no"];
        $this->title       = $array["title"];
        $this->text        = $array["text"];
        $this->status      = $array["status"];
        $this->submittedAt = $array["submitted_at"];
        $this->updatedAt   = $array["updated_at"];
        $this->reviewedAt  = $array["reviewed_at"];
    }

    public function __toString()
    {
        return print_r($this->toArray(), true);
    }
}

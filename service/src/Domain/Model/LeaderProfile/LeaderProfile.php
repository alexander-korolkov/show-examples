<?php

namespace Fxtm\CopyTrading\Domain\Model\LeaderProfile;

use Fxtm\CopyTrading\Domain\Common\AbstractEntity;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\DomainException;

class LeaderProfile extends AbstractEntity
{
    private $leaderId    = null;
    private $avatar      = null;
    private $nickname    = null;
    private $useNickname = false;
    private $showName    = false;
    private $showCountry = false;
    private $updatedAt   = null;

    public function __construct(ClientId $leaderId)
    {
        parent::__construct($leaderId);

        $this->leaderId  = $leaderId->value();
        $this->updatedAt = DateTime::NOW();
    }

    public function leaderId()
    {
        return new ClientId($this->leaderId);
    }

    public function avatar()
    {
        return $this->avatar;
    }

    public function nickname()
    {
        return $this->nickname;
    }

    public function setNickname($nickname)
    {
        $this->nickname = $nickname;
        $this->updatedAt = DateTime::NOW();
    }

    public function useNickname($use = true)
    {
        if ($use && empty($this->nickname)) {
            throw new DomainException("Empty nickname");
        }
        $this->useNickname = $use;
        $this->updatedAt = DateTime::NOW();
    }

    public function showName($show = true)
    {
        $this->showName = $show;
        $this->updatedAt = DateTime::NOW();
    }

    public function showCountry($show = true)
    {
        $this->showCountry = $show;
        $this->updatedAt = DateTime::NOW();
    }

    public function changeAvatar($avatar)
    {
        $this->avatar = $avatar;
        $this->updatedAt = DateTime::NOW();
    }

    public function removeAvatar()
    {
        $this->avatar = null;
        $this->updatedAt = DateTime::NOW();
    }

    public function updatedAt()
    {
        return $this->updatedAt;
    }

    public function toArray()
    {
        return [
            "leader_id"    => $this->leaderId,
            "avatar"       => $this->avatar,
            "nickname"     => $this->nickname,
            "use_nickname" => $this->useNickname,
            "show_name"    => $this->showName,
            "show_country" => $this->showCountry,
            "updated_at"   => $this->updatedAt->__toString(),
        ];
    }

    public function fromArray(array $array)
    {
        $this->leaderId    = $array["leader_id"];
        $this->avatar      = $array["avatar"];
        $this->nickname    = $array["nickname"];
        $this->useNickname = $array["use_nickname"];
        $this->showName    = $array["show_name"];
        $this->showCountry = $array["show_country"];
        $this->updatedAt   = DateTime::of($array["updated_at"]);
    }

    public function getUseNickname()
    {
        return $this->useNickname;
    }

    public function getShowName()
    {
        return $this->showName;
    }

    public function getShowCountry()
    {
        return $this->showCountry;
    }
}

<?php

namespace Fxtm\CopyTrading\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Fxtm\CopyTrading\Interfaces\Repository\EquityRepository")
 * @ORM\Table(name="equities")
 */
class Equity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    private $acc_no;

    /**
     * @ORM\Column(type="datetime_immutable")
     *
     * @var DateTimeImmutable
     */
    private $date_time;

    /**
     * @ORM\Column(type="decimal", precision=16, scale=4)
     *
     * @var string
     */
    private $equity;

    /**
     * @ORM\Column(type="decimal", precision=16, scale=4)
     *
     * @var string
     */
    private $in_out;

    public function getId() : ?int
    {
        return $this->id;
    }

    public function getAccNo() : ?int
    {
        return $this->acc_no;
    }

    public function getDateTime() : ?DateTimeImmutable
    {
        return $this->date_time;
    }

    public function getEquityAsString() : ?string
    {
        return $this->equity;
    }

    public function getInOutAsString() : ?string
    {
        return $this->in_out;
    }

    public function setProperties(int $accNo, DateTimeImmutable $date, string $equity, string $inout)
    {
        $this->acc_no = $accNo;
        $this->date_time = $date;
        $this->equity = number_format((float) $equity, 4, '.', '');
        $this->in_out = $inout;
    }
}

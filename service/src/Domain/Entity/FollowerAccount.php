<?php

namespace Fxtm\CopyTrading\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(
 *      name="follower_accounts",
 *      uniqueConstraints={@ORM\UniqueConstraint(name="follower_acc_no", columns={"acc_no"})},
 *      indexes={
 *          @ORM\Index(name="follower_owner_id", columns={"owner_id"}),
 *          @ORM\Index(name="follower_opened_at", columns={"opened_at"}),
 *          @ORM\Index(name="follower_closed_at", columns={"closed_at"}),
 *          @ORM\Index(name="lead_acc_no", columns={"lead_acc_no"}),
 *          @ORM\Index(name="settled_at", columns={"settled_at"}),
 *          @ORM\Index(name="activated_at", columns={"activated_at"})
 *      }
 * )
 * @ORM\Entity(repositoryClass="Fxtm\CopyTrading\Interfaces\Repository\FollowerAccountRepository")
 */
class FollowerAccount
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="acc_no", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $accNo;

    /**
     * @var string
     *
     * @ORM\Column(name="broker", type="string", length=255, nullable=false)
     */
    private $broker;

    /**
     * @var int
     *
     * @ORM\Column(name="lead_acc_no", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $leadAccNo;

    /**
     * @var int
     *
     * @ORM\Column(name="owner_id", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $ownerId;

    /**
     * @var string
     *
     * @ORM\Column(name="acc_curr", type="string", length=3, nullable=false, options={"fixed"=true})
     */
    private $accCurr;

    /**
     * @var string
     *
     * @ORM\Column(name="copy_coef", type="decimal", precision=3, scale=2, nullable=false)
     */
    private $copyCoef;

    /**
     * @var bool
     *
     * @ORM\Column(name="lock_copy_coef", type="boolean", nullable=false)
     */
    private $lockCopyCoef = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="stoploss_level", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $stoplossLevel;

    /**
     * @var string
     *
     * @ORM\Column(name="stoploss_equity", type="decimal", precision=15, scale=2, nullable=false)
     */
    private $stoplossEquity;

    /**
     * @var bool
     *
     * @ORM\Column(name="stoploss_action", type="boolean", nullable=false)
     */
    private $stoplossAction;

    /**
     * @var int
     *
     * @ORM\Column(name="pay_fee", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $payFee;

    /**
     * @var string
     *
     * @ORM\Column(name="balance", type="decimal", precision=15, scale=2, nullable=false)
     */
    private $balance;

    /**
     * @var string
     *
     * @ORM\Column(name="equity", type="decimal", precision=16, scale=4, nullable=false, options={"default"="0.0000"})
     */
    private $equity = '0.0000';

    /**
     * @var bool
     *
     * @ORM\Column(name="status", type="boolean", nullable=false)
     */
    private $status;

    /**
     * @var bool
     *
     * @ORM\Column(name="state", type="boolean", nullable=false)
     */
    private $state;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_copying", type="boolean", nullable=false)
     */
    private $isCopying;

    /**
     * @var bool
     *
     * @ORM\Column(name="lock_copying", type="boolean", nullable=false)
     */
    private $lockCopying = '0';

    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(name="opened_at", type="datetime_immutable", nullable=false)
     */
    private $openedAt;

    /**
     * @var DateTimeImmutable|null
     *
     * @ORM\Column(name="closed_at", type="datetime_immutable", nullable=true)
     */
    private $closedAt;

    /**
     * @var DateTimeImmutable|null
     *
     * @ORM\Column(name="activated_at", type="datetime_immutable", nullable=true)
     */
    private $activatedAt;

    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(name="settled_at", type="datetime_immutable", nullable=false)
     */
    private $settledAt;

    /**
     * @var string|null
     *
     * @ORM\Column(name="settling_equity", type="decimal", precision=15, scale=2, nullable=true)
     */
    private $settlingEquity;
}

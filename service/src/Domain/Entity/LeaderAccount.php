<?php

namespace Fxtm\CopyTrading\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(
 *      name="leader_accounts",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(name="acc_name_2", columns={"acc_name"}),
 *          @ORM\UniqueConstraint(name="prev_acc_name", columns={"prev_acc_name"}),
 *          @ORM\UniqueConstraint(name="acc_no", columns={"acc_no"}),
 *          @ORM\UniqueConstraint(name="acc_name", columns={"acc_name"}),
 *          @ORM\UniqueConstraint(name="aggr_acc_no", columns={"aggr_acc_no"}),
 *          @ORM\UniqueConstraint(name="prev_acc_name_2", columns={"prev_acc_name"})
 *      },
 *      indexes={
 *          @ORM\Index(name="owner_id", columns={"owner_id"}),
 *          @ORM\Index(name="closed_at", columns={"closed_at"}),
 *          @ORM\Index(name="opened_at", columns={"opened_at"})
 *      }
 * )
 * @ORM\Entity(repositoryClass="Fxtm\CopyTrading\Interfaces\Repository\LeaderAccountRepository")
 */
class LeaderAccount
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
     * @var bool
     *
     * @ORM\Column(name="server", type="boolean", nullable=false)
     */
    private $server;

    /**
     * @var int|null
     *
     * @ORM\Column(name="aggr_acc_no", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $aggrAccNo;

    /**
     * @var int
     *
     * @ORM\Column(name="owner_id", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $ownerId;

    /**
     * @var string
     *
     * @ORM\Column(name="acc_name", type="string", length=64, nullable=false)
     */
    private $accName;

    /**
     * @var string|null
     *
     * @ORM\Column(name="prev_acc_name", type="string", length=64, nullable=true)
     */
    private $prevAccName;

    /**
     * @var string
     *
     * @ORM\Column(name="acc_curr", type="string", length=3, nullable=false, options={"fixed"=true})
     */
    private $accCurr;

    /**
     * @var string|null
     *
     * @ORM\Column(name="acc_descr", type="string", length=1024, nullable=true)
     */
    private $accDescr;

    /**
     * @var int
     *
     * @ORM\Column(name="remun_fee", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $remunFee;

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
     * @var string
     *
     * @ORM\Column(name="aggr_acc_equity", type="decimal", precision=16, scale=4, nullable=false, options={"default"="0.0000"})
     */
    private $aggrAccEquity = '0.0000';

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
     * @ORM\Column(name="is_copied", type="boolean", nullable=false)
     */
    private $isCopied = '0';

    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(name="opened_at", type="datetime_immutable", nullable=false)
     */
    private $openedAt;

    /**
     * @var DateTimeImmutable|null
     *
     * @ORM\Column(name="activated_at", type="datetime_immutable", nullable=true)
     */
    private $activatedAt;

    /**
     * @var DateTimeImmutable|null
     *
     * @ORM\Column(name="closed_at", type="datetime_immutable", nullable=true)
     */
    private $closedAt;

    /**
     * @var bool
     *
     * @ORM\Column(name="prepare_stats", type="boolean", nullable=false)
     */
    private $prepareStats;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_public", type="boolean", nullable=false, options={"default"="1"})
     */
    private $isPublic = '1';

    /**
     * @var bool|null
     *
     * @ORM\Column(name="hidden_reason", type="boolean", nullable=true)
     */
    private $hiddenReason;

    /**
     * @var bool
     *
     * @ORM\Column(name="inact_notice", type="boolean", nullable=false)
     */
    private $inactNotice = '0';

    /**
     * @var bool
     *
     * @ORM\Column(name="is_followable", type="boolean", nullable=false, options={"default"="1"})
     */
    private $isFollowable = '1';
}

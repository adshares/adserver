<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Payments
 *
 * @ORM\Table(name="payments")
 * @ORM\Entity
 */
class Payments
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="deleted_at", type="datetime", nullable=true)
     */
    private $deletedAt;

    /**
     * @var string|null
     *
     * @ORM\Column(name="transfers", type="text", length=65535, nullable=true)
     */
    private $transfers;

    /**
     * @var string|null
     *
     * @ORM\Column(name="subthreshold_transfers", type="text", length=65535, nullable=true)
     */
    private $subthresholdTransfers;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="account_address", type="binary", nullable=true)
     */
    private $accountAddress;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="account_hashin", type="binary", nullable=true)
     */
    private $accountHashin;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="account_hashout", type="binary", nullable=true)
     */
    private $accountHashout;

    /**
     * @var int|null
     *
     * @ORM\Column(name="account_msid", type="integer", nullable=true)
     */
    private $accountMsid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="tx_data", type="text", length=65535, nullable=true)
     */
    private $txData;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="tx_id", type="binary", nullable=true)
     */
    private $txId;

    /**
     * @var int|null
     *
     * @ORM\Column(name="tx_time", type="integer", nullable=true)
     */
    private $txTime;

    /**
     * @var int|null
     *
     * @ORM\Column(name="fee", type="bigint", nullable=true, options={"unsigned"=true})
     */
    private $fee;

    /**
     * @var string
     *
     * @ORM\Column(name="state", type="string", length=0, nullable=false)
     */
    private $state;

    /**
     * @var bool
     *
     * @ORM\Column(name="completed", type="boolean", nullable=false)
     */
    private $completed = '0';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getTransfers(): ?string
    {
        return $this->transfers;
    }

    public function setTransfers(?string $transfers): self
    {
        $this->transfers = $transfers;

        return $this;
    }

    public function getSubthresholdTransfers(): ?string
    {
        return $this->subthresholdTransfers;
    }

    public function setSubthresholdTransfers(?string $subthresholdTransfers): self
    {
        $this->subthresholdTransfers = $subthresholdTransfers;

        return $this;
    }

    public function getAccountAddress()
    {
        return $this->accountAddress;
    }

    public function setAccountAddress($accountAddress): self
    {
        $this->accountAddress = $accountAddress;

        return $this;
    }

    public function getAccountHashin()
    {
        return $this->accountHashin;
    }

    public function setAccountHashin($accountHashin): self
    {
        $this->accountHashin = $accountHashin;

        return $this;
    }

    public function getAccountHashout()
    {
        return $this->accountHashout;
    }

    public function setAccountHashout($accountHashout): self
    {
        $this->accountHashout = $accountHashout;

        return $this;
    }

    public function getAccountMsid(): ?int
    {
        return $this->accountMsid;
    }

    public function setAccountMsid(?int $accountMsid): self
    {
        $this->accountMsid = $accountMsid;

        return $this;
    }

    public function getTxData(): ?string
    {
        return $this->txData;
    }

    public function setTxData(?string $txData): self
    {
        $this->txData = $txData;

        return $this;
    }

    public function getTxId()
    {
        return $this->txId;
    }

    public function setTxId($txId): self
    {
        $this->txId = $txId;

        return $this;
    }

    public function getTxTime(): ?int
    {
        return $this->txTime;
    }

    public function setTxTime(?int $txTime): self
    {
        $this->txTime = $txTime;

        return $this;
    }

    public function getFee(): ?int
    {
        return $this->fee;
    }

    public function setFee(?int $fee): self
    {
        $this->fee = $fee;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getCompleted(): ?bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): self
    {
        $this->completed = $completed;

        return $this;
    }


}

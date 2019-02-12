<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NetworkPayments
 *
 * @ORM\Table(name="network_payments")
 * @ORM\Entity
 */
class NetworkPayments
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
     * @var binary|null
     *
     * @ORM\Column(name="receiver_address", type="binary", nullable=true)
     */
    private $receiverAddress;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="sender_address", type="binary", nullable=true)
     */
    private $senderAddress;

    /**
     * @var string|null
     *
     * @ORM\Column(name="sender_host", type="string", length=32, nullable=true)
     */
    private $senderHost;

    /**
     * @var int
     *
     * @ORM\Column(name="amount", type="bigint", nullable=false)
     */
    private $amount;

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
     * @var bool
     *
     * @ORM\Column(name="detailed_data_used", type="boolean", nullable=false)
     */
    private $detailedDataUsed = '0';

    /**
     * @var bool
     *
     * @ORM\Column(name="processed", type="boolean", nullable=false)
     */
    private $processed = '0';

    /**
     * @var int|null
     *
     * @ORM\Column(name="ads_payment_id", type="bigint", nullable=true)
     */
    private $adsPaymentId;

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

    public function getReceiverAddress()
    {
        return $this->receiverAddress;
    }

    public function setReceiverAddress($receiverAddress): self
    {
        $this->receiverAddress = $receiverAddress;

        return $this;
    }

    public function getSenderAddress()
    {
        return $this->senderAddress;
    }

    public function setSenderAddress($senderAddress): self
    {
        $this->senderAddress = $senderAddress;

        return $this;
    }

    public function getSenderHost(): ?string
    {
        return $this->senderHost;
    }

    public function setSenderHost(?string $senderHost): self
    {
        $this->senderHost = $senderHost;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

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

    public function getDetailedDataUsed(): ?bool
    {
        return $this->detailedDataUsed;
    }

    public function setDetailedDataUsed(bool $detailedDataUsed): self
    {
        $this->detailedDataUsed = $detailedDataUsed;

        return $this;
    }

    public function getProcessed(): ?bool
    {
        return $this->processed;
    }

    public function setProcessed(bool $processed): self
    {
        $this->processed = $processed;

        return $this;
    }

    public function getAdsPaymentId(): ?int
    {
        return $this->adsPaymentId;
    }

    public function setAdsPaymentId(?int $adsPaymentId): self
    {
        $this->adsPaymentId = $adsPaymentId;

        return $this;
    }


}

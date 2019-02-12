<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NetworkEventLogs
 *
 * @ORM\Table(name="network_event_logs", uniqueConstraints={@ORM\UniqueConstraint(name="network_event_logs_event_id_unique", columns={"event_id"})})
 * @ORM\Entity
 */
class NetworkEventLogs
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
     * @var binary|null
     *
     * @ORM\Column(name="case_id", type="binary", nullable=true)
     */
    private $caseId;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="event_id", type="binary", nullable=true)
     */
    private $eventId;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="user_id", type="binary", nullable=true)
     */
    private $userId;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="banner_id", type="binary", nullable=true)
     */
    private $bannerId;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="publisher_id", type="binary", nullable=true)
     */
    private $publisherId;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="site_id", type="binary", nullable=true)
     */
    private $siteId;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="zone_id", type="binary", nullable=true)
     */
    private $zoneId;

    /**
     * @var string
     *
     * @ORM\Column(name="event_type", type="string", length=16, nullable=false)
     */
    private $eventType;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="pay_from", type="binary", nullable=true)
     */
    private $payFrom;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="ip", type="binary", nullable=true)
     */
    private $ip;

    /**
     * @var json|null
     *
     * @ORM\Column(name="headers", type="json", nullable=true)
     */
    private $headers;

    /**
     * @var string|null
     *
     * @ORM\Column(name="context", type="text", length=65535, nullable=true)
     */
    private $context;

    /**
     * @var int|null
     *
     * @ORM\Column(name="human_score", type="integer", nullable=true)
     */
    private $humanScore;

    /**
     * @var string|null
     *
     * @ORM\Column(name="our_userdata", type="text", length=65535, nullable=true)
     */
    private $ourUserdata;

    /**
     * @var string|null
     *
     * @ORM\Column(name="their_userdata", type="text", length=65535, nullable=true)
     */
    private $theirUserdata;

    /**
     * @var int|null
     *
     * @ORM\Column(name="event_value", type="bigint", nullable=true)
     */
    private $eventValue;

    /**
     * @var int|null
     *
     * @ORM\Column(name="paid_amount", type="bigint", nullable=true)
     */
    private $paidAmount;

    /**
     * @var int|null
     *
     * @ORM\Column(name="licence_fee_amount", type="bigint", nullable=true)
     */
    private $licenceFeeAmount;

    /**
     * @var int|null
     *
     * @ORM\Column(name="operator_fee_amount", type="bigint", nullable=true)
     */
    private $operatorFeeAmount;

    /**
     * @var int|null
     *
     * @ORM\Column(name="ads_payment_id", type="bigint", nullable=true)
     */
    private $adsPaymentId;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_view_clicked", type="boolean", nullable=false)
     */
    private $isViewClicked = '0';

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

    public function getCaseId()
    {
        return $this->caseId;
    }

    public function setCaseId($caseId): self
    {
        $this->caseId = $caseId;

        return $this;
    }

    public function getEventId()
    {
        return $this->eventId;
    }

    public function setEventId($eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserId($userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getBannerId()
    {
        return $this->bannerId;
    }

    public function setBannerId($bannerId): self
    {
        $this->bannerId = $bannerId;

        return $this;
    }

    public function getPublisherId()
    {
        return $this->publisherId;
    }

    public function setPublisherId($publisherId): self
    {
        $this->publisherId = $publisherId;

        return $this;
    }

    public function getSiteId()
    {
        return $this->siteId;
    }

    public function setSiteId($siteId): self
    {
        $this->siteId = $siteId;

        return $this;
    }

    public function getZoneId()
    {
        return $this->zoneId;
    }

    public function setZoneId($zoneId): self
    {
        $this->zoneId = $zoneId;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getPayFrom()
    {
        return $this->payFrom;
    }

    public function setPayFrom($payFrom): self
    {
        $this->payFrom = $payFrom;

        return $this;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setIp($ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function setHeaders(?array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getHumanScore(): ?int
    {
        return $this->humanScore;
    }

    public function setHumanScore(?int $humanScore): self
    {
        $this->humanScore = $humanScore;

        return $this;
    }

    public function getOurUserdata(): ?string
    {
        return $this->ourUserdata;
    }

    public function setOurUserdata(?string $ourUserdata): self
    {
        $this->ourUserdata = $ourUserdata;

        return $this;
    }

    public function getTheirUserdata(): ?string
    {
        return $this->theirUserdata;
    }

    public function setTheirUserdata(?string $theirUserdata): self
    {
        $this->theirUserdata = $theirUserdata;

        return $this;
    }

    public function getEventValue(): ?int
    {
        return $this->eventValue;
    }

    public function setEventValue(?int $eventValue): self
    {
        $this->eventValue = $eventValue;

        return $this;
    }

    public function getPaidAmount(): ?int
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(?int $paidAmount): self
    {
        $this->paidAmount = $paidAmount;

        return $this;
    }

    public function getLicenceFeeAmount(): ?int
    {
        return $this->licenceFeeAmount;
    }

    public function setLicenceFeeAmount(?int $licenceFeeAmount): self
    {
        $this->licenceFeeAmount = $licenceFeeAmount;

        return $this;
    }

    public function getOperatorFeeAmount(): ?int
    {
        return $this->operatorFeeAmount;
    }

    public function setOperatorFeeAmount(?int $operatorFeeAmount): self
    {
        $this->operatorFeeAmount = $operatorFeeAmount;

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

    public function getIsViewClicked(): ?bool
    {
        return $this->isViewClicked;
    }

    public function setIsViewClicked(bool $isViewClicked): self
    {
        $this->isViewClicked = $isViewClicked;

        return $this;
    }


}

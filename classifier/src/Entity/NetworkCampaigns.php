<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NetworkCampaigns
 *
 * @ORM\Table(name="network_campaigns", uniqueConstraints={@ORM\UniqueConstraint(name="network_campaigns_uuid_unique", columns={"uuid"})})
 * @ORM\Entity
 */
class NetworkCampaigns
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
     * @var binary
     *
     * @ORM\Column(name="uuid", type="binary", nullable=false)
     */
    private $uuid;

    /**
     * @var binary
     *
     * @ORM\Column(name="demand_campaign_id", type="binary", nullable=false)
     */
    private $demandCampaignId;

    /**
     * @var binary
     *
     * @ORM\Column(name="publisher_id", type="binary", nullable=false)
     */
    private $publisherId;

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
     * @ORM\Column(name="source_created_at", type="datetime", nullable=true)
     */
    private $sourceCreatedAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="source_updated_at", type="datetime", nullable=true)
     */
    private $sourceUpdatedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="source_host", type="string", length=255, nullable=false)
     */
    private $sourceHost;

    /**
     * @var string
     *
     * @ORM\Column(name="source_version", type="string", length=16, nullable=false)
     */
    private $sourceVersion;

    /**
     * @var string
     *
     * @ORM\Column(name="source_address", type="string", length=32, nullable=false)
     */
    private $sourceAddress;

    /**
     * @var string
     *
     * @ORM\Column(name="landing_url", type="string", length=1024, nullable=false)
     */
    private $landingUrl;

    /**
     * @var int
     *
     * @ORM\Column(name="max_cpm", type="bigint", nullable=false)
     */
    private $maxCpm;

    /**
     * @var int
     *
     * @ORM\Column(name="max_cpc", type="bigint", nullable=false)
     */
    private $maxCpc;

    /**
     * @var int
     *
     * @ORM\Column(name="budget", type="bigint", nullable=false)
     */
    private $budget;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_start", type="datetime", nullable=false)
     */
    private $dateStart;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="date_end", type="datetime", nullable=true)
     */
    private $dateEnd;

    /**
     * @var bool
     *
     * @ORM\Column(name="status", type="boolean", nullable=false, options={"default"="1"})
     */
    private $status = '1';

    /**
     * @var json|null
     *
     * @ORM\Column(name="targeting_excludes", type="json", nullable=true)
     */
    private $targetingExcludes;

    /**
     * @var json|null
     *
     * @ORM\Column(name="targeting_requires", type="json", nullable=true)
     */
    private $targetingRequires;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getDemandCampaignId()
    {
        return $this->demandCampaignId;
    }

    public function setDemandCampaignId($demandCampaignId): self
    {
        $this->demandCampaignId = $demandCampaignId;

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

    public function getSourceCreatedAt(): ?\DateTimeInterface
    {
        return $this->sourceCreatedAt;
    }

    public function setSourceCreatedAt(?\DateTimeInterface $sourceCreatedAt): self
    {
        $this->sourceCreatedAt = $sourceCreatedAt;

        return $this;
    }

    public function getSourceUpdatedAt(): ?\DateTimeInterface
    {
        return $this->sourceUpdatedAt;
    }

    public function setSourceUpdatedAt(?\DateTimeInterface $sourceUpdatedAt): self
    {
        $this->sourceUpdatedAt = $sourceUpdatedAt;

        return $this;
    }

    public function getSourceHost(): ?string
    {
        return $this->sourceHost;
    }

    public function setSourceHost(string $sourceHost): self
    {
        $this->sourceHost = $sourceHost;

        return $this;
    }

    public function getSourceVersion(): ?string
    {
        return $this->sourceVersion;
    }

    public function setSourceVersion(string $sourceVersion): self
    {
        $this->sourceVersion = $sourceVersion;

        return $this;
    }

    public function getSourceAddress(): ?string
    {
        return $this->sourceAddress;
    }

    public function setSourceAddress(string $sourceAddress): self
    {
        $this->sourceAddress = $sourceAddress;

        return $this;
    }

    public function getLandingUrl(): ?string
    {
        return $this->landingUrl;
    }

    public function setLandingUrl(string $landingUrl): self
    {
        $this->landingUrl = $landingUrl;

        return $this;
    }

    public function getMaxCpm(): ?int
    {
        return $this->maxCpm;
    }

    public function setMaxCpm(int $maxCpm): self
    {
        $this->maxCpm = $maxCpm;

        return $this;
    }

    public function getMaxCpc(): ?int
    {
        return $this->maxCpc;
    }

    public function setMaxCpc(int $maxCpc): self
    {
        $this->maxCpc = $maxCpc;

        return $this;
    }

    public function getBudget(): ?int
    {
        return $this->budget;
    }

    public function setBudget(int $budget): self
    {
        $this->budget = $budget;

        return $this;
    }

    public function getDateStart(): ?\DateTimeInterface
    {
        return $this->dateStart;
    }

    public function setDateStart(\DateTimeInterface $dateStart): self
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?\DateTimeInterface
    {
        return $this->dateEnd;
    }

    public function setDateEnd(?\DateTimeInterface $dateEnd): self
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTargetingExcludes(): ?array
    {
        return $this->targetingExcludes;
    }

    public function setTargetingExcludes(?array $targetingExcludes): self
    {
        $this->targetingExcludes = $targetingExcludes;

        return $this;
    }

    public function getTargetingRequires(): ?array
    {
        return $this->targetingRequires;
    }

    public function setTargetingRequires(?array $targetingRequires): self
    {
        $this->targetingRequires = $targetingRequires;

        return $this;
    }


}

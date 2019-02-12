<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Campaigns
 *
 * @ORM\Table(name="campaigns", uniqueConstraints={@ORM\UniqueConstraint(name="campaigns_uuid_unique", columns={"uuid"})}, indexes={@ORM\Index(name="campaigns_user_id_foreign", columns={"user_id"})})
 * @ORM\Entity
 */
class Campaigns
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
     * @var string
     *
     * @ORM\Column(name="landing_url", type="string", length=1024, nullable=false)
     */
    private $landingUrl;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="time_start", type="datetime", nullable=false)
     */
    private $timeStart;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="time_end", type="datetime", nullable=true)
     */
    private $timeEnd;

    /**
     * @var bool
     *
     * @ORM\Column(name="status", type="boolean", nullable=false)
     */
    private $status = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false, options={"default"="<name>"})
     */
    private $name = '<name>';

    /**
     * @var int
     *
     * @ORM\Column(name="max_cpm", type="bigint", nullable=false)
     */
    private $maxCpm = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="max_cpc", type="bigint", nullable=false)
     */
    private $maxCpc = '0';

    /**
     * @var int
     *
     * @ORM\Column(name="budget", type="bigint", nullable=false)
     */
    private $budget = '0';

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

    /**
     * @var bool
     *
     * @ORM\Column(name="classification_status", type="boolean", nullable=false)
     */
    private $classificationStatus = '0';

    /**
     * @var string|null
     *
     * @ORM\Column(name="classification_tags", type="string", length=255, nullable=true)
     */
    private $classificationTags;

    /**
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     * })
     */
    private $user;

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

    public function getLandingUrl(): ?string
    {
        return $this->landingUrl;
    }

    public function setLandingUrl(string $landingUrl): self
    {
        $this->landingUrl = $landingUrl;

        return $this;
    }

    public function getTimeStart(): ?\DateTimeInterface
    {
        return $this->timeStart;
    }

    public function setTimeStart(\DateTimeInterface $timeStart): self
    {
        $this->timeStart = $timeStart;

        return $this;
    }

    public function getTimeEnd(): ?\DateTimeInterface
    {
        return $this->timeEnd;
    }

    public function setTimeEnd(?\DateTimeInterface $timeEnd): self
    {
        $this->timeEnd = $timeEnd;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getClassificationStatus(): ?bool
    {
        return $this->classificationStatus;
    }

    public function setClassificationStatus(bool $classificationStatus): self
    {
        $this->classificationStatus = $classificationStatus;

        return $this;
    }

    public function getClassificationTags(): ?string
    {
        return $this->classificationTags;
    }

    public function setClassificationTags(?string $classificationTags): self
    {
        $this->classificationTags = $classificationTags;

        return $this;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user): self
    {
        $this->user = $user;

        return $this;
    }


}

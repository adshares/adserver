<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Banners
 *
 * @ORM\Table(name="banners", uniqueConstraints={@ORM\UniqueConstraint(name="banners_uuid_unique", columns={"uuid"})}, indexes={@ORM\Index(name="banners_campaign_id_foreign", columns={"campaign_id"})})
 * @ORM\Entity
 */
class Banners
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
     * @var string|null
     *
     * @ORM\Column(name="creative_contents", type="blob", length=16777215, nullable=true)
     */
    private $creativeContents;

    /**
     * @var string
     *
     * @ORM\Column(name="creative_type", type="string", length=32, nullable=false)
     */
    private $creativeType;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="creative_sha1", type="binary", nullable=true)
     */
    private $creativeSha1;

    /**
     * @var int
     *
     * @ORM\Column(name="creative_width", type="integer", nullable=false)
     */
    private $creativeWidth;

    /**
     * @var int
     *
     * @ORM\Column(name="creative_height", type="integer", nullable=false)
     */
    private $creativeHeight;

    /**
     * @var string|null
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @var bool
     *
     * @ORM\Column(name="status", type="boolean", nullable=false)
     */
    private $status = '0';

    /**
     * @var \Campaigns
     *
     * @ORM\ManyToOne(targetEntity="Campaigns")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="campaign_id", referencedColumnName="id")
     * })
     */
    private $campaign;

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

    public function getCreativeContents()
    {
        return $this->creativeContents;
    }

    public function setCreativeContents($creativeContents): self
    {
        $this->creativeContents = $creativeContents;

        return $this;
    }

    public function getCreativeType(): ?string
    {
        return $this->creativeType;
    }

    public function setCreativeType(string $creativeType): self
    {
        $this->creativeType = $creativeType;

        return $this;
    }

    public function getCreativeSha1()
    {
        return $this->creativeSha1;
    }

    public function setCreativeSha1($creativeSha1): self
    {
        $this->creativeSha1 = $creativeSha1;

        return $this;
    }

    public function getCreativeWidth(): ?int
    {
        return $this->creativeWidth;
    }

    public function setCreativeWidth(int $creativeWidth): self
    {
        $this->creativeWidth = $creativeWidth;

        return $this;
    }

    public function getCreativeHeight(): ?int
    {
        return $this->creativeHeight;
    }

    public function setCreativeHeight(int $creativeHeight): self
    {
        $this->creativeHeight = $creativeHeight;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getCampaign(): ?Campaigns
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaigns $campaign): self
    {
        $this->campaign = $campaign;

        return $this;
    }


}

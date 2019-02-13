<?php

namespace App\Entity;

use function bin2hex;
use Doctrine\ORM\Mapping as ORM;

/**
 * NetworkBanners
 *
 * @ORM\Table(name="network_banners", indexes={@ORM\Index(name="network_banners_network_campaign_id_foreign", columns={"network_campaign_id"})})
 * @ORM\Entity
 */
class NetworkBanners
{
    private const IMAGE_TYPE = 'image';
    private const HTML_TYPE = 'html';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="uuid", type="string", nullable=true)
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
     * @ORM\Column(name="serve_url", type="string", length=1024, nullable=false)
     */
    private $serveUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="click_url", type="string", length=1024, nullable=false)
     */
    private $clickUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="view_url", type="string", length=1024, nullable=false)
     */
    private $viewUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=32, nullable=false)
     */
    private $type;

    /**
     * @var binary|null
     *
     * @ORM\Column(name="checksum", type="binary", nullable=true)
     */
    private $checksum;

    /**
     * @var int
     *
     * @ORM\Column(name="width", type="integer", nullable=false)
     */
    private $width;

    /**
     * @var int
     *
     * @ORM\Column(name="height", type="integer", nullable=false)
     */
    private $height;

    /**
     * @var bool
     *
     * @ORM\Column(name="status", type="boolean", nullable=false, options={"default"="1"})
     */
    private $status = '1';

    /**
     * @var \NetworkCampaigns
     *
     * @ORM\ManyToOne(targetEntity="NetworkCampaigns")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="network_campaign_id", referencedColumnName="id")
     * })
     */
    private $networkCampaign;

    private $content;

    private $html;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return bin2hex($this->uuid);
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

    public function getServeUrl(): ?string
    {
        return $this->serveUrl;
    }

    public function setServeUrl(string $serveUrl): self
    {
        $this->serveUrl = $serveUrl;

        return $this;
    }

    public function getClickUrl(): ?string
    {
        return $this->clickUrl;
    }

    public function setClickUrl(string $clickUrl): self
    {
        $this->clickUrl = $clickUrl;

        return $this;
    }

    public function getViewUrl(): ?string
    {
        return $this->viewUrl;
    }

    public function setViewUrl(string $viewUrl): self
    {
        $this->viewUrl = $viewUrl;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getChecksum()
    {
        return $this->checksum;
    }

    public function setChecksum($checksum): self
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

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

    public function getNetworkCampaign(): ?NetworkCampaigns
    {
        return $this->networkCampaign;
    }

    public function setNetworkCampaign(?NetworkCampaigns $networkCampaign): self
    {
        $this->networkCampaign = $networkCampaign;

        return $this;
    }

    public function getContent(): string
    {
        return $this->getServeUrl();
    }

    public function getHtml(): string
    {
        return $this->getServeUrl();
    }

    public function isImage(): bool
    {
        return $this->type === self::IMAGE_TYPE;
    }
}

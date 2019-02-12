<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Sites
 *
 * @ORM\Table(name="sites", uniqueConstraints={@ORM\UniqueConstraint(name="sites_uuid_unique", columns={"uuid"})}, indexes={@ORM\Index(name="sites_user_id_foreign", columns={"user_id"})})
 * @ORM\Entity
 */
class Sites
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
     * @var binary|null
     *
     * @ORM\Column(name="uuid", type="binary", nullable=true)
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
     * @ORM\Column(name="name", type="string", length=64, nullable=false)
     */
    private $name;

    /**
     * @var bool
     *
     * @ORM\Column(name="status", type="boolean", nullable=false, options={"default"="1"})
     */
    private $status = '1';

    /**
     * @var json|null
     *
     * @ORM\Column(name="site_excludes", type="json", nullable=true)
     */
    private $siteExcludes;

    /**
     * @var json|null
     *
     * @ORM\Column(name="site_requires", type="json", nullable=true)
     */
    private $siteRequires;

    /**
     * @var string
     *
     * @ORM\Column(name="primary_language", type="string", length=2, nullable=false, options={"default"="en"})
     */
    private $primaryLanguage = 'en';

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
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

    public function getSiteExcludes(): ?array
    {
        return $this->siteExcludes;
    }

    public function setSiteExcludes(?array $siteExcludes): self
    {
        $this->siteExcludes = $siteExcludes;

        return $this;
    }

    public function getSiteRequires(): ?array
    {
        return $this->siteRequires;
    }

    public function setSiteRequires(?array $siteRequires): self
    {
        $this->siteRequires = $siteRequires;

        return $this;
    }

    public function getPrimaryLanguage(): ?string
    {
        return $this->primaryLanguage;
    }

    public function setPrimaryLanguage(string $primaryLanguage): self
    {
        $this->primaryLanguage = $primaryLanguage;

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

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NetworkHosts
 *
 * @ORM\Table(name="network_hosts", uniqueConstraints={@ORM\UniqueConstraint(name="network_hosts_address_unique", columns={"address"})})
 * @ORM\Entity
 */
class NetworkHosts
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
     * @var string
     *
     * @ORM\Column(name="address", type="string", length=18, nullable=false)
     */
    private $address;

    /**
     * @var string
     *
     * @ORM\Column(name="host", type="string", length=128, nullable=false)
     */
    private $host;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_broadcast", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $lastBroadcast = 'CURRENT_TIMESTAMP';

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
     * @var bool
     *
     * @ORM\Column(name="failed_connection", type="boolean", nullable=false)
     */
    private $failedConnection = '0';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getLastBroadcast(): ?\DateTimeInterface
    {
        return $this->lastBroadcast;
    }

    public function setLastBroadcast(\DateTimeInterface $lastBroadcast): self
    {
        $this->lastBroadcast = $lastBroadcast;

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

    public function getFailedConnection(): ?bool
    {
        return $this->failedConnection;
    }

    public function setFailedConnection(bool $failedConnection): self
    {
        $this->failedConnection = $failedConnection;

        return $this;
    }


}

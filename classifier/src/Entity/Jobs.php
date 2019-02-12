<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Jobs
 *
 * @ORM\Table(name="jobs", indexes={@ORM\Index(name="jobs_queue", columns={"queue"})})
 * @ORM\Entity
 */
class Jobs
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
     * @ORM\Column(name="queue", type="string", length=191, nullable=false)
     */
    private $queue;

    /**
     * @var string
     *
     * @ORM\Column(name="payload", type="text", length=0, nullable=false)
     */
    private $payload;

    /**
     * @var bool
     *
     * @ORM\Column(name="attempts", type="boolean", nullable=false)
     */
    private $attempts;

    /**
     * @var int|null
     *
     * @ORM\Column(name="reserved_at", type="integer", nullable=true, options={"unsigned"=true})
     */
    private $reservedAt;

    /**
     * @var int
     *
     * @ORM\Column(name="available_at", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $availableAt;

    /**
     * @var int
     *
     * @ORM\Column(name="created_at", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function setQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getAttempts(): ?bool
    {
        return $this->attempts;
    }

    public function setAttempts(bool $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getReservedAt(): ?int
    {
        return $this->reservedAt;
    }

    public function setReservedAt(?int $reservedAt): self
    {
        $this->reservedAt = $reservedAt;

        return $this;
    }

    public function getAvailableAt(): ?int
    {
        return $this->availableAt;
    }

    public function setAvailableAt(int $availableAt): self
    {
        $this->availableAt = $availableAt;

        return $this;
    }

    public function getCreatedAt(): ?int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }


}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Migrations
 *
 * @ORM\Table(name="migrations")
 * @ORM\Entity
 */
class Migrations
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="migration", type="string", length=255, nullable=false)
     */
    private $migration;

    /**
     * @var int
     *
     * @ORM\Column(name="batch", type="integer", nullable=false)
     */
    private $batch;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMigration(): ?string
    {
        return $this->migration;
    }

    public function setMigration(string $migration): self
    {
        $this->migration = $migration;

        return $this;
    }

    public function getBatch(): ?int
    {
        return $this->batch;
    }

    public function setBatch(int $batch): self
    {
        $this->batch = $batch;

        return $this;
    }


}

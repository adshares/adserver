<?php

declare(strict_types=1);

namespace Adshares\Adserver\Utilities;

use Adshares\Lib\Id;
use Ramsey\Uuid\UuidInterface;

final class UniqueId implements Id
{
    /** @var UuidInterface */
    private $id;

    public function __construct(UuidInterface $id)
    {
        $this->id = $id;
    }

    public function __toString(): string
    {
        return $this->id->toString();
    }
}

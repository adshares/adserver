<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 5:48 PM
 */
declare(strict_types=1);

namespace Internals;

use Lib\Id;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UniqueId implements Id
{
    /** @var UuidInterface */
    private $id;

    public function __toString(): string
    {
        return $this->id->toString();
    }

    public static function fromString(string $id): self
    {
        $uuid = new self();
        $uuid->id = Uuid::fromString($id);

        return $uuid;
    }

    public static function random(): self
    {
        $uuid = new self();
        $uuid->id = Uuid::uuid4();

        return $uuid;
    }

    private function __construct() { }
}

<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 5:48 PM
 */
declare(strict_types=1);

namespace AdServer;

use Lib\Id;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UniqueIdentifier implements Id
{
    /** @var UuidInterface */
    private $id;

    public function toString(): string
    {
        return $this->id->toString();
    }

    public static function fromString(string $id): Id
    {
        $uniqueIdentifier = new UniqueIdentifier();
        $uniqueIdentifier->id = Uuid::fromString($id);

        return $uniqueIdentifier;
    }
}

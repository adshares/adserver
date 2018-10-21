<?php
declare(strict_types=1);

namespace Lib;

interface Id
{
    public function toString(): string;

    public static function fromString(string $id): Id;
}

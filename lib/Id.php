<?php
declare(strict_types=1);

namespace Lib;

interface Id
{
    public function __toString(): string;

    public static function fromString(string $id);

    public static function random();
}

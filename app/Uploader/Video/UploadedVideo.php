<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace Adshares\Adserver\Uploader\Video;

use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Supply\Domain\ValueObject\Size;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UploadedVideo implements UploadedFile
{
    private string $name;
    private string $previewUrl;
    private array $size;

    public function __construct(string $name, string $previewUrl, array $size)
    {
        $aspect = self::getAspect($size);
        if (!Size::isValid($aspect)) {
            throw new BadRequestHttpException('Unsupported video aspect: ' . $aspect);
        }

        $this->name = $name;
        $this->previewUrl = $previewUrl;
        $this->size = $size;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->previewUrl,
            'size' => $this->getFormattedSize($this->size),
        ];
    }

    private function getFormattedSize(array $size): string
    {
        if (isset($size[0], $size[1])) {
            return Size::fromDimensions((int)$size[0], (int)$size[1]);
        }

        return '';
    }

    public static function getAspect(array $size): string
    {
        if (!isset($size[0], $size[1]) || !is_int($size[0]) || !is_int($size[1])) {
            return '';
        }

        $a = $size[0];
        $b = $size[1];
        while ($b !== 0) {
            $c = $a % $b;
            $a = $b;
            $b = $c;
        }

        return $size[0] / $a . ':' . $size[1] / $a;
    }
}

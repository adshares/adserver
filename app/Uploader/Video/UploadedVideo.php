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
    private int $width;
    private int $height;

    public function __construct(string $name, string $previewUrl, int $width, int $height)
    {
        $aspect = Size::getAspect($width, $height);
        if (!Size::isValid($aspect)) {
            throw new BadRequestHttpException('Unsupported video aspect: ' . $aspect);
        }

        $this->name = $name;
        $this->previewUrl = $previewUrl;
        $this->width = $width;
        $this->height = $height;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->previewUrl,
            'size' => Size::fromDimensions($this->width, $this->height),
        ];
    }
}

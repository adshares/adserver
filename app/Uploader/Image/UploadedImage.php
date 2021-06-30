<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Uploader\Image;

use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Supply\Domain\ValueObject\Size;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function in_array;

class UploadedImage implements UploadedFile
{
    /** @var string */
    private $name;
    /** @var string */
    private $previewUrl;
    /** @var array */
    private $size;

    public function __construct(string $name, string $previewUrl, array $size)
    {
        $formattedSize = $this->getFormattedSize($size);
        if (!Size::isValid($formattedSize)) {
            throw new BadRequestHttpException('Unsupported image size: ' . $formattedSize);
        }

        $this->name = $name;
        $this->previewUrl = $previewUrl;
        $this->size = $size;
    }

    public function toArray(): array
    {
        $formattedSize = $this->getFormattedSize($this->size);

        return [
            'name' => $this->name,
            'url' => $this->previewUrl,
            'size' => $formattedSize,
        ];
    }

    private function getFormattedSize(array $size): string
    {
        if (isset($size[0], $size[1])) {
            return Size::fromDimensions((int)$size[0], (int)$size[1]);
        }

        return '';
    }
}

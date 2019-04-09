<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Uploader\Image;

use Adshares\Adserver\Uploader\UploadedFile;

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
        $this->name = $name;
        $this->previewUrl = $previewUrl;
        $this->size = $size;
    }

    public function toArray(): array
    {
        $formattedSize = '';

        if (isset($this->size[0], $this->size[1])) {
            $formattedSize = sprintf('%sx%s', $this->size[0], $this->size[1]);
        }

        return [
            'name' => $this->name,
            'url' => $this->previewUrl,
            'size' => $formattedSize,
        ];
    }
}

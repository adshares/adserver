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

namespace Adshares\Adserver\Uploader;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Uploader\DirectLink\DirectLinkUploader;
use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\Video\VideoUploader;
use Adshares\Adserver\Uploader\Html\HtmlUploader;
use Illuminate\Http\Request;

class Factory
{
    public static function createFromType(string $type, Request $request): Uploader
    {
        return match ($type) {
            Banner::TEXT_TYPE_HTML => new HtmlUploader($request),
            Banner::TEXT_TYPE_DIRECT_LINK => new DirectLinkUploader($request),
            Banner::TEXT_TYPE_VIDEO => new VideoUploader($request),
            Banner::TEXT_TYPE_MODEL => new ModelUploader($request),
            default => new ImageUploader($request),
        };
    }
}

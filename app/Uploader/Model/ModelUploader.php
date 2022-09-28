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

namespace Adshares\Adserver\Uploader\Model;

use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ModelUploader implements Uploader
{
    public const MODEL_FILE = 'model';
    private const DISK = 'banners';

    public function __construct(private readonly Request $request)
    {
    }

    public function upload(Medium $medium): UploadedFile
    {
        $file = $this->request->file('file');
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_model')) {
            throw new RuntimeException('Invalid model size');
        }
        $name = $file->storeAs('', Str::random(40) . '.' . $file->getClientOriginalExtension(), self::DISK);
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::MODEL_FILE, 'name' => $name])
        );

        return new UploadedModel($name, $previewUrl->toString());
    }

    public function removeTemporaryFile(string $fileName): void
    {
        Storage::disk(self::DISK)->delete($fileName);
    }

    public function preview(string $fileName): Response
    {
        $content = self::content($fileName);
        $mime = self::contentMimeType($content);

        $response = new Response($content);
        $response->header('Content-Type', $mime);

        return $response;
    }

    public static function content(string $fileName): string
    {
        return Storage::disk(self::DISK)->get($fileName);
    }

    public static function contentMimeType(string $content): string
    {
        $fileHeader = substr($content, 0, 4);
        switch ($fileHeader) {
            case 'glTF':
                $mime = 'model/gltf-binary';
                break;
            case 'VOX ':
                $mime = 'model/voxel';
                break;
            default:
                throw new RuntimeException('Unsupported model file.');
        }
        return $mime;
    }
}

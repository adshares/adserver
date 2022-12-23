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

use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ModelUploader implements Uploader
{
    public const MODEL_FILE = 'model';

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

        $model = new UploadedFileModel([
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => self::contentMimeType($file->getContent()),
            'scope' => 'cube',
            'content' => $file->getContent(),
        ]);
        $model->saveOrFail();

        $name = $model->ulid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::MODEL_FILE, 'uid' => $name])
        );

        return new UploadedModel($name, $previewUrl->toString());
    }

    public function removeTemporaryFile(string $fileName): void
    {
        try {
            UploadedFileModel::fetchByUlidOrFail($fileName)->delete();
        } catch (ModelNotFoundException $exception) {
            Log::warning(sprintf('Exception during model file deletion (%s)', $exception->getMessage()));
        }
    }

    public function preview(string $fileName): Response
    {
        $file = UploadedFileModel::fetchByUlidOrFail($fileName);
        $response = new Response($file->content);
        $response->header('Content-Type', $file->mime);

        return $response;
    }

    private static function contentMimeType(string $content): string
    {
        $fileHeader = substr($content, 0, 4);
        return match ($fileHeader) {
            'glTF' => 'model/gltf-binary',
            'VOX ' => 'model/voxel',
            default => throw new RuntimeException('Unsupported model file'),
        };
    }
}

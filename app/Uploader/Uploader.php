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

namespace Adshares\Adserver\Uploader;

use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;

abstract class Uploader
{
    abstract public function upload(Medium $medium): UploadedFile;

    public function preview(UuidInterface $uuid): Response
    {
        $file = UploadedFileModel::fetchByUuidOrFail($uuid);
        $response = new Response($file->content);
        $response->header('Content-Type', $file->mime);
        return $response;
    }

    public static function removeTemporaryFile(UuidInterface $uuid): bool
    {
        try {
            UploadedFileModel::fetchByUuidOrFail($uuid)->delete();
            return true;
        } catch (ModelNotFoundException $exception) {
            Log::warning(sprintf('Exception during uploaded file deletion (%s)', $exception->getMessage()));
            return false;
        }
    }
}

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

namespace Adshares\Adserver\Uploader\Html;

use Adshares\Adserver\Http\Requests\Campaign\BannerValidator;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Lib\ZipToHtml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HtmlUploader extends Uploader
{
    private const MIME_ZIP_LIST = [
        'application/zip',
        'application/x-compressed',
        'multipart/x-zip',
        'application/octet-stream',
        'application/x-zip',
        'application/x-zip-compressed',
    ];
    private const RESULTANT_MIME_TYPE = 'text/html';
    private const ZIP_DISK = 'banners';

    public function __construct(private readonly Request $request)
    {
    }

    public function upload(Medium $medium): UploadedFile
    {
        $scope = $this->request->get('scope');
        if (!is_string($scope)) {
            throw new RuntimeException('Field `scope` must be a string');
        }
        $file = $this->request->file('file');
        if (null === $file) {
            throw new RuntimeException('Field `file` is required');
        }
        if (!in_array($file->getMimeType(), self::MIME_ZIP_LIST, true)) {
            throw new RuntimeException('File must be a zip archive');
        }
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_zip')) {
            throw new RuntimeException('Invalid zip size');
        }

        $bannerValidator = new BannerValidator($medium);
        $bannerValidator->validateScope(Banner::TEXT_TYPE_HTML, $scope);
        $bannerValidator->validateMimeType(Banner::TEXT_TYPE_HTML, self::RESULTANT_MIME_TYPE);

        $name = $file->store('', self::ZIP_DISK);
        $content = $this->extractHtmlContent($name);
        Storage::disk(self::ZIP_DISK)->delete($name);

        $model = new UploadedFileModel([
            'type' => Banner::TEXT_TYPE_HTML,
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => self::RESULTANT_MIME_TYPE,
            'scope' => $scope,
            'content' => $content,
        ]);
        Auth::user()->uploadedFiles()->save($model);

        $name = $model->uuid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => Banner::TEXT_TYPE_HTML, 'uuid' => $name])
        );

        return new UploadedHtml($name, $previewUrl->toString());
    }

    private function extractHtmlContent(string $name): string
    {
        $path = Storage::disk(self::ZIP_DISK)->path($name);
        $zip = new ZipToHtml($path);
        return $zip->getHtml();
    }
}

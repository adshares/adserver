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

namespace Adshares\Adserver\Tests\Uploader\Html;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Html\UploadedHtml;
use Adshares\Adserver\Uploader\Html\HtmlUploader;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;

final class HtmlUploaderTest extends TestCase
{
    public function testUpload(): void
    {
        $uploader = new HtmlUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        $uploaded = $uploader->upload($medium);

        self::assertInstanceOf(UploadedHtml::class, $uploaded);
        self::assertDatabaseHas(UploadedFileModel::class, [
            'mime' => 'text/html',
            'scope' => '300x250',
        ]);
    }

    public function testUploadFailWhileScopeIsMissing(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::any())
            ->method('file')
            ->willReturn(UploadedFile::fake()->createWithContent(
                'a.zip',
                file_get_contents(base_path('tests/mock/Files/Banners/300x250.zip'))
            ));
        $uploader = new HtmlUploader($request);
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testUploadFailWhileFileIsMissing(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::any())
            ->method('get')
            ->with('scope')
            ->willReturn('300x250');
        $uploader = new HtmlUploader($request);
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testUploadEmpty(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(
                UploadedFile::fake()->createWithContent(
                    'a.zip',
                    file_get_contents(base_path('tests/mock/Files/Banners/empty.zip'))
                )
            );
        $request->expects(self::once())
            ->method('get')
            ->with('scope')
            ->willReturn('300x250');
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        (new HtmlUploader($request))->upload($medium);
    }

    public function testUploadImageInsteadOfZip(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(
                UploadedFile::fake()->createWithContent(
                    'a.png',
                    file_get_contents(base_path('tests/mock/Files/Banners/980x120.png'))
                )
            );
        $request->expects(self::once())
            ->method('get')
            ->with('scope')
            ->willReturn('300x250');
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        (new HtmlUploader($request))->upload($medium);
    }

    public function testUploadFailWhileSizeTooLarge(): void
    {
        Config::updateAdminSettings([Config::UPLOAD_LIMIT_ZIP => 0]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $uploader = new HtmlUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testRemoveTemporaryFile(): void
    {
        $file = UploadedFileModel::factory()->create();
        $uploader = new HtmlUploader(self::createMock(Request::class));

        $result = $uploader->removeTemporaryFile(Uuid::fromString($file->uuid));

        self::assertTrue($result);
        self::assertDatabaseMissing(UploadedFileModel::class, ['id' => $file->id]);
    }

    public function testRemoveTemporaryFileQuietError(): void
    {
        $uploader = new HtmlUploader(self::createMock(Request::class));

        $result = $uploader->removeTemporaryFile(Uuid::fromString('971a7dfe-feec-48fc-808a-4c50ccb3a9c6'));

        self::assertFalse($result);
    }

    public function testPreview(): void
    {
        $file = UploadedFileModel::factory()->create([
            'mime' => 'text/html',
            'content' => 'html content',
        ]);
        $uploader = new HtmlUploader(self::createMock(Request::class));

        $response = $uploader->preview(Uuid::fromString($file->uuid));

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals('html content', $response->getContent());
    }

    private function getRequestMock(): Request|MockObject
    {
        Auth::shouldReceive('guard')->andReturnSelf()
            ->shouldReceive('user')->andReturn(User::factory()->create());
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(UploadedFile::fake()->createWithContent(
                'a.zip',
                file_get_contents(base_path('tests/mock/Files/Banners/300x250.zip'))
            ));
        $request->expects(self::once())
            ->method('get')
            ->with('scope')
            ->willReturn('300x250');
        return $request;
    }
}

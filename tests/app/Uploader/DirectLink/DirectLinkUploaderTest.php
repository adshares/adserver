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

namespace Adshares\Adserver\Tests\Uploader\DirectLink;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\DirectLink\DirectLinkUploader;
use Adshares\Adserver\Uploader\DirectLink\UploadedDirectLink;
use Adshares\Adserver\Uploader\Html\HtmlUploader;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\MockObject\MockObject;

final class DirectLinkUploaderTest extends TestCase
{
    public function testUpload(): void
    {
        $uploader = new DirectLinkUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        $uploaded = $uploader->upload($medium);

        self::assertInstanceOf(UploadedDirectLink::class, $uploaded);
        self::assertDatabaseHas(UploadedFileModel::class, [
            'mime' => 'text/plain',
            'scope' => 'pop-up',
        ]);
    }

    public function testUploadFailWhileScopeIsMissing(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::any())
            ->method('file')
            ->willReturn(UploadedFile::fake()->createWithContent(
                'a.txt',
                'https://example.com'
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
            ->willReturn('pop-up');
        $uploader = new HtmlUploader($request);
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testUploadFailWhileSizeTooLarge(): void
    {
        Config::updateAdminSettings([Config::UPLOAD_LIMIT_DIRECT_LINK => 10]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $uploader = new DirectLinkUploader($this->getRequestMock('300x250'));
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    private function getRequestMock(string $scope = 'pop-up'): Request|MockObject
    {
        Auth::shouldReceive('guard')->andReturnSelf()
            ->shouldReceive('user')->andReturn(User::factory()->create());
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(UploadedFile::fake()->createWithContent(
                'a.txt',
                'https://example.com'
            ));
        $request->expects(self::once())
            ->method('get')
            ->with('scope')
            ->willReturn($scope);
        return $request;
    }
}

<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\SupplyBannerPlaceholder;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Supply\BannerPlaceholderProvider;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\PlaceholderUploader;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

final class SupplyBannerPlaceholderControllerTest extends TestCase
{
    private const URI_PLACEHOLDER = '/api/v2/creatives/placeholder';
    private const PLACEHOLDERS_STRUCTURE = [
        'data' => [
            '*' => [
                'id',
                'createdAt',
                'updatedAt',
                'medium',
                'scope',
                'type',
                'mime',
                'isDefault',
                'checksum',
                'url',
            ],
        ]
    ];

    public function testFetchPlaceholders(): void
    {
        $this->setUpAdmin();
        SupplyBannerPlaceholder::factory()->create(
            [
                'medium' => 'metaverse',
                'size' => '512x512',
            ]
        );
        SupplyBannerPlaceholder::factory()->create(
            [
                'is_default' => true,
                'size' => '728x90',
            ]
        );

        $response = $this->getJson(self::URI_PLACEHOLDER);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::PLACEHOLDERS_STRUCTURE);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'medium' => 'metaverse',
            'scope' => '512x512',
            'type' => 'image',
            'mime' => 'image/png',
            'isDefault' => false,
        ]);
        $response->assertJsonFragment([
            'medium' => 'web',
            'scope' => '728x90',
            'type' => 'image',
            'mime' => 'image/png',
            'isDefault' => true,
        ]);
    }

    public function testFetchPlaceholdersMetaverse(): void
    {
        $this->setUpAdmin();
        SupplyBannerPlaceholder::factory()->create(
            [
                'medium' => 'metaverse',
                'size' => '512x512',
            ]
        );
        SupplyBannerPlaceholder::factory()->create(
            [
                'is_default' => true,
                'size' => '728x90',
            ]
        );
        $query = http_build_query(
            [
                'filter' => [
                    'medium' => 'metaverse',
                ],
            ]
        );

        $response = $this->getJson(self::URI_PLACEHOLDER . '?' . $query);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::PLACEHOLDERS_STRUCTURE);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'medium' => 'metaverse',
            'scope' => '512x512',
            'type' => 'image',
            'mime' => 'image/png',
            'isDefault' => false,
        ]);
    }

    public function testDeletePlaceholder(): void
    {
        $this->setUpAdmin();
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create(
            [
                'medium' => 'metaverse',
                'size' => '512x512',
            ]
        );

        $response = $this->deleteJson(self::URI_PLACEHOLDER . '/' . $placeholder->uuid);

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertDatabaseMissing(
            SupplyBannerPlaceholder::class,
            [
                'id' => $placeholder->id,
            ]
        );
    }

    public function testDeletePlaceholderFailWhileProviderException(): void
    {
        $this->setUpAdmin();
        $providerMock = self::createMock(BannerPlaceholderProvider::class);
        $providerMock->method('deleteBannerPlaceholder')
            ->willThrowException(new RuntimeException('test-exception'));
        $this->app->bind(BannerPlaceholderProvider::class, fn() => $providerMock);
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create();

        $response = $this->deleteJson(self::URI_PLACEHOLDER . '/' . $placeholder->uuid);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeletePlaceholderFailWhileInvalidId(): void
    {
        $this->setUpAdmin();

        $response = $this->deleteJson(self::URI_PLACEHOLDER . '/132');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeletePlaceholderFailWhileNotExist(): void
    {
        $this->setUpAdmin();

        $response = $this->deleteJson(self::URI_PLACEHOLDER . '/10000000000000000000000000000000');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testUploadPlaceholder(): void
    {
        $this->setUpAdmin();
        $file = UploadedFile::fake()->image('test.png', 300, 250);

        $response = $this->post(self::URI_PLACEHOLDER, [
            'medium' => 'web',
            'type' => 'image',
            'file-0' => $file,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Location');
        self::assertDatabaseHas(
            SupplyBannerPlaceholder::class,
            [
                'medium' => 'web',
                'type' => 'image',
                'mime' => 'image/png',
                'size' => '300x250',
                'is_default' => false,
            ],
        );
    }

    public function testUploadPlaceholderFailWhileUploaderThrowsError(): void
    {
        $uploader = self::createMock(PlaceholderUploader::class);
        $uploader->method('upload')
            ->willThrowException(new RuntimeException('test-exception'));
        $this->app->bind(PlaceholderUploader::class, fn() => $uploader);
        $this->setUpAdmin();
        $file = UploadedFile::fake()->image('test.png', 300, 250);

        $response = $this->post(self::URI_PLACEHOLDER, [
            'medium' => 'web',
            'type' => 'image',
            'file-0' => $file,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadPlaceholderFailWhileUnsupportedType(): void
    {
        $this->setUpAdmin();
        $file = UploadedFile::fake()->image('test.mp4', 300, 250);

        $response = $this->post(self::URI_PLACEHOLDER, [
            'medium' => 'web',
            'type' => 'video',
            'file-0' => $file,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadPlaceholderFailWhileNoMedium(): void
    {
        $this->setUpAdmin();
        $file = UploadedFile::fake()->image('test.png', 300, 250);

        $response = $this->post(self::URI_PLACEHOLDER, [
            'type' => 'image',
            'file-0' => $file,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadPlaceholderFailWhileInvalidMedium(): void
    {
        $this->setUpAdmin();
        $file = UploadedFile::fake()->image('test.png', 300, 250);

        $response = $this->post(self::URI_PLACEHOLDER, [
            'medium' => 'invalid',
            'type' => 'image',
            'file-0' => $file,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadPlaceholderFailWhileNoFiles(): void
    {
        $this->setUpAdmin();

        $response = $this->post(self::URI_PLACEHOLDER, [
            'medium' => 'web',
            'type' => 'image',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function setUpAdmin(): void
    {
        $user = User::factory()->admin()->create();
        Passport::actingAs($user, [], 'jwt');
    }
}

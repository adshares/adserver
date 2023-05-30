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
use Adshares\Adserver\Tests\TestCase;
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
                'vendor',
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
                'vendor' => 'decentraland',
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
            'vendor' => 'decentraland',
            'scope' => '512x512',
            'type' => 'image',
            'mime' => 'image/png',
            'isDefault' => false,
        ]);
        $response->assertJsonFragment([
            'medium' => 'web',
            'vendor' => null,
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
                'vendor' => 'decentraland',
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
            'vendor' => 'decentraland',
            'scope' => '512x512',
            'type' => 'image',
            'mime' => 'image/png',
            'isDefault' => false,
        ]);
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
        self::assertDatabaseCount(SupplyBannerPlaceholder::class, 1);
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

    public function testUploadPlaceholderFailWhileInvalidVendor(): void
    {
        $this->setUpAdmin();
        $file = UploadedFile::fake()->image('test.png', 300, 250);

        $response = $this->post(self::URI_PLACEHOLDER, [
            'medium' => 'metaverse',
            'vendor' => 1,
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

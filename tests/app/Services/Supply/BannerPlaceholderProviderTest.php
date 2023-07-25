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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SupplyBannerPlaceholder;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Services\Supply\BannerPlaceholderProvider;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Adserver\ViewModel\MetaverseVendor;
use Adshares\Common\Exception\RuntimeException;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;

class BannerPlaceholderProviderTest extends TestCase
{
    private const EXPECTED_FOUND_BANNERS_KEYS = [
        'click_url',
        'creative_sha1',
        'id',
        'info_box',
        'pay_from',
        'pay_to',
        'publisher_id',
        'request_id',
        'rpm',
        'serve_url',
        'size',
        'type',
        'view_url',
        'zone_id',
    ];

    public function testAddBannerPlaceholder(): void
    {
        /** @var SupplyBannerPlaceholder $defaultPlaceholder */
        $defaultPlaceholder = SupplyBannerPlaceholder::factory()->create(
            [
                'is_default' => true,
                'size' => '300x250',
            ]
        );
        $placeholderData = [
            'medium' => MediumName::Web->value,
            'size' => '300x250',
            'type' => 'image',
            'mime' => 'image/png',
            'content' => UploadedFile::fake()
                ->image('test.png', 300, 250)
                ->size(100)
                ->getContent(),
            'groupUuid' => $this->faker->uuid,
        ];
        $placeholderProvider = new BannerPlaceholderProvider();

        $placeholderProvider->addBannerPlaceholder(...$placeholderData);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertTrue($defaultPlaceholder->refresh()->trashed());
    }

    public function testAddBannerPlaceholderOverwriteCustom(): void
    {
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create(['size' => '300x250']);
        $placeholderData = [
            'medium' => MediumName::Web->value,
            'size' => '300x250',
            'type' => 'image',
            'mime' => 'image/png',
            'content' => UploadedFile::fake()
                ->image('test.png', 300, 250)
                ->size(100)
                ->getContent(),
            'groupUuid' => $this->faker->uuid,
        ];
        $placeholderProvider = new BannerPlaceholderProvider();

        $placeholderProvider->addBannerPlaceholder(...$placeholderData);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertDatabaseMissing(SupplyBannerPlaceholder::class, ['id' => $placeholder->id]);
    }

    public function testAddBannerPlaceholderFailOnDbException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new PDOException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        /** @var SupplyBannerPlaceholder $defaultPlaceholder */
        $defaultPlaceholder = SupplyBannerPlaceholder::factory()->create(
            [
                'is_default' => true,
                'size' => '300x250',
            ]
        );
        $placeholderData = [
            'medium' => MediumName::Web->value,
            'size' => '300x250',
            'type' => 'image',
            'mime' => 'image/png',
            'content' => UploadedFile::fake()
                ->image('test.png', 300, 250)
                ->size(100)
                ->getContent(),
            'groupUuid' => $this->faker->uuid,
        ];
        $placeholderProvider = new BannerPlaceholderProvider();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Saving placeholder failed');

        $placeholderProvider->addBannerPlaceholder(...$placeholderData);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertFalse($defaultPlaceholder->refresh()->trashed());
    }

    public function testAddDefaultBannerPlaceholderDoNotOverwrite(): void
    {
        /** @var SupplyBannerPlaceholder $defaultPlaceholder */
        $defaultPlaceholder = SupplyBannerPlaceholder::factory()->create(
            [
                'is_default' => true,
                'size' => '300x250',
            ]
        );
        $placeholderData = [
            'medium' => MediumName::Web->value,
            'size' => '300x250',
            'type' => 'image',
            'mime' => 'image/png',
            'content' => UploadedFile::fake()
                ->image('test.png', 300, 250)
                ->size(100)
                ->getContent(),
            'groupUuid' => $this->faker->uuid,
            'forceOverwrite' => false,
        ];
        $placeholderProvider = new BannerPlaceholderProvider();

        $newDefaultPlaceholder = $placeholderProvider->addDefaultBannerPlaceholder(...$placeholderData);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertDatabaseHas(SupplyBannerPlaceholder::class, ['id' => $defaultPlaceholder->id]);
        self::assertFalse($newDefaultPlaceholder->trashed());
        self::assertEquals($defaultPlaceholder->id, $newDefaultPlaceholder->id);
    }

    public function testAddDefaultBannerPlaceholderOverwriteWithoutCustom(): void
    {
        /** @var SupplyBannerPlaceholder $defaultPlaceholder */
        $defaultPlaceholder = SupplyBannerPlaceholder::factory()->create(
            [
                'is_default' => true,
                'size' => '300x250',
            ]
        );
        $placeholderData = [
            'medium' => MediumName::Web->value,
            'size' => '300x250',
            'type' => 'image',
            'mime' => 'image/png',
            'content' => UploadedFile::fake()
                ->image('test.png', 300, 250)
                ->size(100)
                ->getContent(),
            'groupUuid' => $this->faker->uuid,
        ];
        $placeholderProvider = new BannerPlaceholderProvider();

        $newDefaultPlaceholder = $placeholderProvider->addDefaultBannerPlaceholder(...$placeholderData);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertDatabaseMissing(SupplyBannerPlaceholder::class, ['id' => $defaultPlaceholder->id]);
        self::assertDatabaseHas(SupplyBannerPlaceholder::class, ['id' => $newDefaultPlaceholder->id]);
        self::assertFalse($newDefaultPlaceholder->trashed());
    }

    public function testAddDefaultBannerPlaceholderOverwriteWithCustom(): void
    {
        /** @var SupplyBannerPlaceholder $defaultPlaceholder */
        $defaultPlaceholder = SupplyBannerPlaceholder::factory()->create(
            [
                'deleted_at' => new DateTimeImmutable(),
                'is_default' => true,
                'size' => '300x250',
            ]
        );
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create(['size' => '300x250']);
        $placeholderData = [
            'medium' => MediumName::Web->value,
            'size' => '300x250',
            'type' => 'image',
            'mime' => 'image/png',
            'content' => UploadedFile::fake()
                ->image('test.png', 300, 250)
                ->size(100)
                ->getContent(),
            'groupUuid' => $this->faker->uuid,
        ];
        $placeholderProvider = new BannerPlaceholderProvider();

        $newDefaultPlaceholder = $placeholderProvider->addDefaultBannerPlaceholder(...$placeholderData);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertDatabaseMissing(SupplyBannerPlaceholder::class, ['id' => $defaultPlaceholder->id]);
        self::assertDatabaseHas(SupplyBannerPlaceholder::class, ['id' => $placeholder->id]);
        self::assertDatabaseHas(SupplyBannerPlaceholder::class, ['id' => $newDefaultPlaceholder->id]);
        self::assertTrue($newDefaultPlaceholder->trashed());
    }

    public function testAddDefaultBannerPlaceholderFailOnDbException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new PDOException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        /** @var SupplyBannerPlaceholder $defaultPlaceholder */
        $defaultPlaceholder = SupplyBannerPlaceholder::factory()->create(
            [
                'is_default' => true,
                'size' => '300x250',
            ]
        );
        $placeholderData = [
            'medium' => MediumName::Web->value,
            'size' => '300x250',
            'type' => 'image',
            'mime' => 'image/png',
            'content' => UploadedFile::fake()
                ->image('test.png', 300, 250)
                ->size(100)
                ->getContent(),
            'groupUuid' => $this->faker->uuid,
        ];
        $placeholderProvider = new BannerPlaceholderProvider();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Saving placeholder failed');

        $placeholderProvider->addDefaultBannerPlaceholder(...$placeholderData);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertFalse($defaultPlaceholder->refresh()->trashed());
    }

    public function testDeleteBannerPlaceholders(): void
    {
        /** @var SupplyBannerPlaceholder $defaultPlaceholder */
        $defaultPlaceholder = SupplyBannerPlaceholder::factory()->create(
            [
                'deleted_at' => new DateTimeImmutable(),
                'is_default' => true,
                'size' => '300x250',
            ]
        );
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create(['size' => '300x250']);
        /** @var SupplyBannerPlaceholder $derivedPlaceholder */
        $derivedPlaceholder = SupplyBannerPlaceholder::factory()->create([
            'group_uuid' => $placeholder->group_uuid,
            'mime' => 'image/jpeg',
            'size' => '300x250',
        ]);
        $placeholderProvider = new BannerPlaceholderProvider();

        $placeholderProvider->deleteBannerPlaceholder($placeholder);

        self::assertCount(1, SupplyBannerPlaceholder::all());
        self::assertFalse($defaultPlaceholder->refresh()->trashed());
        self::assertDatabaseMissing(SupplyBannerPlaceholder::class, ['id' => $placeholder->id]);
        self::assertDatabaseMissing(SupplyBannerPlaceholder::class, ['id' => $derivedPlaceholder->id]);
    }

    public function testDeleteBannerPlaceholdersWhileDefaultIsMissing(): void
    {
        Log::spy();
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create(['size' => '300x250']);
        $placeholderProvider = new BannerPlaceholderProvider();

        $placeholderProvider->deleteBannerPlaceholder($placeholder);

        self::assertCount(0, SupplyBannerPlaceholder::all());
        self::assertDatabaseMissing(SupplyBannerPlaceholder::class, ['id' => $placeholder->id]);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn($message) => 1 === preg_match(
                '~^Default banner placeholder not found '
                . '\(medium=web, size=[0-9x]+, type=image, mime=image/png\)$~',
                $message,
            ));
    }

    public function testDeleteBannerPlaceholdersFailWhileDefault(): void
    {
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create(['is_default' => true]);
        $placeholderProvider = new BannerPlaceholderProvider();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Cannot delete default placeholder');

        $placeholderProvider->deleteBannerPlaceholder($placeholder);
    }

    /**
     * @dataProvider findBannerPlaceholdersDataProvider
     */
    public function testFindBannerPlaceholders(array $zoneData, bool $success): void
    {
        $uuidRegularZone = '8315d6e8b37b4cc2aa2634422ae714b1';
        $uuidRegularZoneWithoutMatchingPlaceholder = '07828d436e6d48bba825a6cbe040e3b2';
        $uuidPopZone = 'd59cfbc8bfc743eea9dcc691a4b2e839';
        $uuidMetaverseZone = '8c0886bee1db4ff5840681802b08cc03';
        $uuidRegularZoneWithInactiveSite = '00008d436e6d48bba825a6cbe0404444';

        /** @var Zone $regularZone */
        $regularZone = Zone::factory()->create([
            'scopes' => ['300x250'],
            'site_id' => Site::factory()->create(['user_id' => User::factory()->create()]),
            'size' => '300x250',
            'type' => Zone::TYPE_DISPLAY,
        ]);
        /** @var Zone $regularZoneWithoutMatchingPlaceholder */
        $regularZoneWithoutMatchingPlaceholder = Zone::factory()->create([
            'scopes' => ['728x90'],
            'site_id' => Site::factory()->create(['user_id' => User::factory()->create()]),
            'size' => '728x90',
            'type' => Zone::TYPE_DISPLAY,
        ]);
        /** @var Zone $popZone */
        $popZone = Zone::factory()->create([
            'scopes' => ['pop-up'],
            'site_id' => Site::factory()->create(['user_id' => User::factory()->create()]),
            'size' => 'pop-up',
            'type' => Zone::TYPE_POP,
        ]);
        /** @var Zone $metaverseZone */
        $metaverseZone = Zone::factory()->create([
            'scopes' => ['300x250'],
            'site_id' => Site::factory()->create(
                [
                    'medium' => MediumName::Metaverse->value,
                    'user_id' => User::factory()->create(),
                    'vendor' => MetaverseVendor::Decentraland->value,
                ]
            ),
            'size' => '300x250',
            'type' => Zone::TYPE_DISPLAY,
        ]);
        /** @var Zone $regularZoneWithInactiveSite */
        $regularZoneWithInactiveSite = Zone::factory()->create([
            'scopes' => ['300x250'],
            'site_id' => Site::factory()->create(
                [
                    'status' => Site::STATUS_INACTIVE,
                    'user_id' => User::factory()->create(),
                ]
            ),
            'size' => '300x250',
            'type' => Zone::TYPE_DISPLAY,
        ]);
        $regularZone->uuid = $uuidRegularZone;
        $regularZone->save();
        $regularZoneWithoutMatchingPlaceholder->uuid = $uuidRegularZoneWithoutMatchingPlaceholder;
        $regularZoneWithoutMatchingPlaceholder->save();
        $popZone->uuid = $uuidPopZone;
        $popZone->save();
        $metaverseZone->uuid = $uuidMetaverseZone;
        $metaverseZone->save();
        $regularZoneWithInactiveSite->uuid = $uuidRegularZoneWithInactiveSite;
        $regularZoneWithInactiveSite->save();
        $content = UploadedFile::fake()
            ->image('test.png', 300, 250)
            ->size(100)
            ->getContent();
        /** @var SupplyBannerPlaceholder $placeholder */
        $placeholder = SupplyBannerPlaceholder::factory()->create(
            [
                'medium' => MediumName::Web->value,
                'size' => '300x250',
                'type' => 'image',
                'mime' => 'image/png',
                'content' => $content,
                'checksum' => sha1($content),
            ]
        );
        $placeholderProvider = new BannerPlaceholderProvider();

        $foundBanners = $placeholderProvider->findBannerPlaceholders(
            [$zoneData],
            '1234567890ABCDEF1234567890ABCDEF',
        );

        self::assertCount(1, $foundBanners);
        if ($success) {
            $foundBanner = $foundBanners->first();
            self::assertIsArray($foundBanner);

            foreach (self::EXPECTED_FOUND_BANNERS_KEYS as $key) {
                self::assertArrayHasKey($key, $foundBanner);
            }
            self::assertEquals($placeholder->checksum, $foundBanner['creative_sha1']);
            self::assertEquals($placeholder->uuid, $foundBanner['id']);
            self::assertEquals(true, $foundBanner['info_box']);
            self::assertEquals('0001-00000005-CBCA', $foundBanner['pay_from']);
            self::assertEquals('0001-00000005-CBCA', $foundBanner['pay_to']);
            self::assertEquals('00000000000000000000000000000000', $foundBanner['publisher_id']);
            self::assertEquals('1', $foundBanner['request_id']);
            self::assertEquals(0, $foundBanner['rpm']);
            self::assertEquals('300x250', $foundBanner['size']);
            self::assertEquals('image', $foundBanner['type']);
            self::assertEquals($uuidRegularZone, $foundBanner['zone_id']);
        } else {
            self::assertNull($foundBanners->first());
        }
    }

    public function findBannerPlaceholdersDataProvider(): array
    {
        return [
            'find placeholder' => [
                [
                    'id' => '1',
                    'placementId' => '8315d6e8b37b4cc2aa2634422ae714b1',
                    'options' => [
                        'banner_type' => null,
                        'banner_mime' => null,
                    ],
                ],
                true,
            ],
            'find placeholder with matching type' => [
                [
                    'id' => '1',
                    'placementId' => '8315d6e8b37b4cc2aa2634422ae714b1',
                    'options' => [
                        'banner_type' => ['image', 'video'],
                        'banner_mime' => null,
                    ],
                ],
                true,
            ],
            'find placeholder with matching mime' => [
                [
                    'id' => '1',
                    'placementId' => '8315d6e8b37b4cc2aa2634422ae714b1',
                    'options' => [
                        'banner_type' => null,
                        'banner_mime' => ['image/jpeg', 'image/png'],
                    ],
                ],
                true,
            ],
            'missing placeholder for type' => [
                [
                    'id' => '1',
                    'placementId' => '8315d6e8b37b4cc2aa2634422ae714b1',
                    'options' => [
                        'banner_type' => ['video'],
                        'banner_mime' => null,
                    ],
                ],
                false,
            ],
            'missing placeholder for mime' => [
                [
                    'id' => '1',
                    'placementId' => '8315d6e8b37b4cc2aa2634422ae714b1',
                    'options' => [
                        'banner_type' => null,
                        'banner_mime' => ['image/webp'],
                    ],
                ],
                false,
            ],
            'missing placeholder for size' => [
                [
                    'id' => '1',
                    'placementId' => '07828d436e6d48bba825a6cbe040e3b2',
                    'options' => [
                        'banner_type' => null,
                        'banner_mime' => null,
                    ],
                ],
                false,
            ],
            'missing placeholder for popup' => [
                [
                    'id' => '1',
                    'placementId' => 'd59cfbc8bfc743eea9dcc691a4b2e839',
                    'options' => [
                        'banner_type' => null,
                        'banner_mime' => null,
                    ],
                ],
                false,
            ],
            'missing placeholder for metaverse' => [
                [
                    'id' => '1',
                    'placementId' => '8c0886bee1db4ff5840681802b08cc03',
                    'options' => [
                        'banner_type' => null,
                        'banner_mime' => null,
                    ],
                ],
                false,
            ],
            'missing placeholder for zone with inactive site' => [
                [
                    'id' => '1',
                    'placementId' => '00008d436e6d48bba825a6cbe0404444',
                    'options' => [
                        'banner_type' => null,
                        'banner_mime' => null,
                    ],
                ],
                false,
            ],
        ];
    }
}

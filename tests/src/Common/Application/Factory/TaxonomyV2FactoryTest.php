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

namespace Adshares\Tests\Common\Application\Factory;

use Adshares\Common\Application\Dto\TaxonomyV2;
use Adshares\Common\Application\Factory\TaxonomyV2Factory;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function GuzzleHttp\json_decode;

class TaxonomyV2FactoryTest extends TestCase
{
    public function testTaxonomyFromJson(): void
    {
        $taxonomy = TaxonomyV2Factory::fromJson(self::jsonTaxonomy());
        self::assertInstanceOf(TaxonomyV2::class, $taxonomy);
    }

    public function testTaxonomyIntegration(): void
    {
        $taxonomy = TaxonomyV2Factory::fromJson(self::jsonTaxonomy());
        $decentralandMedium = null;
        foreach ($taxonomy->getMedia() as $medium) {
            if ($medium->getVendor() === 'decentraland') {
                $decentralandMedium = $medium;
                break;
            }
        }
        self::assertNotNull($decentralandMedium, 'Medium not found');

        $imageFormat = null;
        foreach ($decentralandMedium->toArray()['formats'] as $format) {
            if ($format['type'] === 'image') {
                $imageFormat = $format;
                break;
            }
        }
        self::assertNotNull($imageFormat, 'Format not found');
        self::assertNotContains('image/gif', $imageFormat['mimes']);
    }

    public function testTaxonomyIntegrationAddNode(): void
    {
        $data = json_decode(self::jsonTaxonomy(), true);
        $data['vendors'][] = self::customVendor();
        $json = json_encode($data);
        $taxonomy = TaxonomyV2Factory::fromJson($json);
        $testMedium = null;
        foreach ($taxonomy->getMedia() as $medium) {
            if ($medium->getVendor() === 'test-vendor') {
                $testMedium = $medium;
                break;
            }
        }
        self::assertNotNull($testMedium, 'Medium not found');

        $testFormat = null;
        foreach ($testMedium->toArray()['formats'] as $format) {
            if ($format['type'] === 'pixel') {
                $testFormat = $format;
                break;
            }
        }
        self::assertNotNull($testFormat, 'Format not found');
    }

    /**
     * @dataProvider vendorKeyProvider
     */
    public function testVendorMissingKey(string $key): void
    {
        $data = json_decode(self::jsonTaxonomy(), true);
        unset($data['vendors'][0][$key]);
        $json = json_encode($data);
        self::expectException(InvalidArgumentException::class);
        TaxonomyV2Factory::fromJson($json);
    }

    /**
     * @dataProvider vendorKeyProvider
     */
    public function testVendorInvalidKey(string $key): void
    {
        $data = json_decode(self::jsonTaxonomy(), true);
        $data['vendors'][] = self::customVendor([$key => 0]);
        $json = json_encode($data);
        self::expectException(InvalidArgumentException::class);
        TaxonomyV2Factory::fromJson($json);
    }

    /**
     * @dataProvider vendorKeyProvider
     */
    public function testVendorChangesMissingKey(string $key): void
    {
        $data = json_decode(self::jsonTaxonomy(), true);
        $data['vendors'][] = self::customVendor([], $key);
        $json = json_encode($data);
        self::expectException(InvalidArgumentException::class);
        TaxonomyV2Factory::fromJson($json);
    }

    public function testVendorForMissingMedium(): void
    {
        $data = json_decode(self::jsonTaxonomy(), true);
        $data['vendors'][] = self::customVendor(['medium' => 'invalid-medium']);
        $json = json_encode($data);

        self::expectException(InvalidArgumentException::class);
        TaxonomyV2Factory::fromJson($json);
    }

    public function vendorKeyProvider(): array
    {
        return [
            'medium' => ['medium'],
            'name' => ['name'],
            'label' => ['label'],
        ];
    }

    private static function jsonTaxonomy(): string
    {
        return file_get_contents('tests/mock/targeting_schema_v2.json');
    }

    private static function customVendor(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'medium' => 'metaverse',
            'name' => 'test-vendor',
            'label' => 'Test Vendor',
            'formats' => [
                [
                    'path' => '$[]',
                    'value' => [
                        'type' => 'pixel',
                        'mimes' => [
                            'image/gif',
                        ],
                        'scopes' => [
                            '1x1' => 'Test Size',
                        ]
                    ]
                ]
            ]
        ], $mergeData);

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }
}

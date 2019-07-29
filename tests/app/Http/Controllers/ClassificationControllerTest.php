<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\ClassifierExternalKeywordsSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use SodiumException;

class ClassificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_KEY = 'FF767FC8FAF9CFA8D2C3BD193663E8B8CAC85005AD56E085FAB179B52BD88DD6';

    private const URI_UPDATE = '/callback/classifications/';

    private const CLASSIFIER_NAME = 'test_classifier';

    public function testUpdateClassification(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE.self::CLASSIFIER_NAME,
            $this->getKeywords($banner)
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $expectedKeywords = [
            'category' => [
                'crypto',
                'gambling',
            ],
        ];

        $this->assertEquals($expectedKeywords, $keywords);
    }

    public function testUpdateClassificationMissingId(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE.self::CLASSIFIER_NAME,
            $this->getKeywordsWithoutId($banner)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateClassificationMissingSignature(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE.self::CLASSIFIER_NAME,
            $this->getKeywordsWithoutSignature($banner)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateClassificationEmpty(): void
    {
        $response = $this->patchJson(
            self::URI_UPDATE.self::CLASSIFIER_NAME,
            $this->getKeywordsEmpty()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function insertBanner(): Banner
    {
        $user = factory(User::class)->create();
        $campaign = factory(Campaign::class)->create(['user_id' => $user->id]);
        $banner = factory(Banner::class)->create(['campaign_id' => $campaign->id]);
        $banner->classifications()->save(BannerClassification::prepare(self::CLASSIFIER_NAME));

        return $banner;
    }

    private function getKeywords(Banner $banner): array
    {
        $keywords = [
            'category' => [
                'crypto',
                'gambling',
            ],
        ];
        $message =
            hash('sha256', hex2bin($banner->creative_sha1).ClassifierExternalKeywordsSerializer::serialize($keywords));
        $signature = $this->sign($message);

        return [
            [
                'id' => $banner->uuid,
                'keywords' => $keywords,
                'signature' => $signature,
            ],
        ];
    }

    private function getKeywordsWithoutId(Banner $banner): array
    {
        $keywords = $this->getKeywords($banner);
        unset($keywords[0]['id']);

        return $keywords;
    }

    private function getKeywordsWithoutSignature(Banner $banner): array
    {
        $keywords = $this->getKeywords($banner);
        unset($keywords[0]['signature']);

        return $keywords;
    }

    private function getKeywordsEmpty(): array
    {
        return [];
    }

    /**
     * @param string $message
     *
     * @return string
     * @throws SodiumException
     */
    private function sign(string $message): string
    {
        $keyPair = sodium_crypto_sign_seed_keypair(hex2bin(self::PRIVATE_KEY));
        $keySecret = sodium_crypto_sign_secretkey($keyPair);

        return bin2hex(sodium_crypto_sign_detached($message, $keySecret));
    }
}

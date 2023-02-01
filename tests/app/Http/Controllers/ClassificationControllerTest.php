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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Client\ClassifierExternalClient;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\ClassifierExternalKeywordsSerializer;
use SodiumException;
use Symfony\Component\HttpFoundation\Response;

use function bin2hex;
use function hash;
use function hex2bin;
use function sodium_crypto_sign_detached;
use function sodium_crypto_sign_secretkey;
use function sodium_crypto_sign_seed_keypair;
use function time;

class ClassificationControllerTest extends TestCase
{
    private const PRIVATE_KEY = 'FF767FC8FAF9CFA8D2C3BD193663E8B8CAC85005AD56E085FAB179B52BD88DD6';

    private const URI_UPDATE = '/callback/classifications/';

    private const CLASSIFIER_NAME = 'test_classifier';

    public function testFetch(): void
    {
        $user = $this->login();
        /** @var Site $site */
        $site = Site::factory()->create(['medium' => 'metaverse', 'user_id' => $user, 'vendor' => 'decentraland']);
        $campaignDecentraland = NetworkCampaign::factory()
            ->create(['medium' => 'metaverse', 'vendor' => 'decentraland']);
        /** @var NetworkBanner $bannerDecentraland */
        $bannerDecentraland = NetworkBanner::factory()->create(['network_campaign_id' => $campaignDecentraland]);
        $campaignMetaverse = NetworkCampaign::factory()->create(['medium' => 'metaverse', 'vendor' => null]);
        /** @var NetworkBanner $bannerMetaverse */
        $bannerMetaverse = NetworkBanner::factory()->create(['network_campaign_id' => $campaignMetaverse]);
        $campaignWeb = NetworkCampaign::factory()->create(['medium' => 'web', 'vendor' => null]);
        NetworkBanner::factory()->create(['network_campaign_id' => $campaignWeb]);
        $expectedBannerIds = [$bannerDecentraland->id, $bannerMetaverse->id];

        $response = $this->getJson(sprintf('/api/classifications/%d', $site->id));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('itemsCount', 2);
        self::assertEqualsCanonicalizing($expectedBannerIds, $response->json('items.*.bannerId'));
    }

    public function testUpdateClassification(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
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

    public function testUpdateClassificationDeletedBanner(): void
    {
        $banner = $this->insertBanner();
        $banner->delete();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywords($banner)
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $this->assertNull($keywords);
    }

    public function testUpdateClassificationMissingId(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsWithoutId($banner)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $this->assertNull($keywords);
    }

    public function testUpdateClassificationMissingSignature(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsWithoutSignature($banner)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $this->assertNull($keywords);
    }

    public function testUpdateClassificationMissingTimestamp(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsWithoutTimestamp($banner)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $this->assertNull($keywords);
    }

    public function testUpdateClassificationInvalidSignature(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsInvalidSignature($banner)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $this->assertNull($keywords);
    }

    public function testUpdateClassificationWithOlderTimestamp(): void
    {
        $banner = $this->insertBanner();

        $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywords($banner)
        );

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsWithOlderTimestamp($banner)
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

    public function testUpdateClassificationError(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsError($banner)
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $bannerClassification = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME);
        $this->assertNull($bannerClassification->keywords);
        $this->assertEquals(BannerClassification::STATUS_FAILURE, $bannerClassification->status);
    }

    public function testUpdateClassificationErrorWithoutCode(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsErrorWithoutCode($banner)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateClassificationEmpty(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . self::CLASSIFIER_NAME,
            $this->getKeywordsEmpty()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $this->assertNull($keywords);
    }

    public function testUpdateClassificationByUnknownClassifier(): void
    {
        $banner = $this->insertBanner();

        $response = $this->patchJson(
            self::URI_UPDATE . 'unknown_classifier',
            $this->getKeywords($banner)
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);

        $keywords = BannerClassification::fetchByBannerIdAndClassifier($banner->id, self::CLASSIFIER_NAME)->keywords;
        $this->assertNull($keywords);
    }

    private function insertBanner(): Banner
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create(['status' => Campaign::STATUS_ACTIVE, 'user_id' => $user->id]);
        $banner = Banner::factory()->create(['campaign_id' => $campaign->id, 'status' => Banner::STATUS_ACTIVE]);
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
        $timestamp = time();
        $message = hash(
            'sha256',
            hex2bin($banner->creative_sha1) . $timestamp . ClassifierExternalKeywordsSerializer::serialize($keywords)
        );
        $signature = $this->sign($message);

        return [
            [
                'id' => $banner->uuid,
                'keywords' => $keywords,
                'signature' => $signature,
                'timestamp' => $timestamp,
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

    private function getKeywordsWithoutTimestamp(Banner $banner): array
    {
        $keywords = $this->getKeywords($banner);
        unset($keywords[0]['timestamp']);

        return $keywords;
    }

    private function getKeywordsInvalidSignature(Banner $banner): array
    {
        $keywords = $this->getKeywords($banner);
        $keywords[0]['signature'] =
            '000000000000000000000000000000000000000000000000000000000000000000000000000000000'
            . '00000000000000000000000000000000000000000000000';

        return $keywords;
    }

    private function getKeywordsWithOlderTimestamp(Banner $banner): array
    {
        $keywords = [
            'category' => [
                'annoying',
            ],
        ];
        $timestamp = 1566463900;
        $message = hash(
            'sha256',
            hex2bin($banner->creative_sha1) . $timestamp . ClassifierExternalKeywordsSerializer::serialize($keywords)
        );
        $signature = $this->sign($message);

        return [
            [
                'id' => $banner->uuid,
                'keywords' => $keywords,
                'signature' => $signature,
                'timestamp' => $timestamp,
            ],
        ];
    }

    private function getKeywordsError(Banner $banner): array
    {
        return [
            [
                'id' => $banner->uuid,
                'error' => [
                    'code' => ClassifierExternalClient::CLASSIFIER_ERROR_CODE_BANNER_REJECTED,
                    'message' => 'Rejected by classifier',
                ],
            ],
        ];
    }

    private function getKeywordsErrorWithoutCode(Banner $banner): array
    {
        return [
            [
                'id' => $banner->uuid,
                'error' => [
                    'description' => 'Rejected by classifier',
                ],
            ],
        ];
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

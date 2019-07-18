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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Client\ClassifierExternalClient;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;
use Adshares\Common\Exception\RuntimeException;
use DateTime;
use Illuminate\Support\Collection;

class BannerClassificationsRequestCommand extends BaseCommand
{
    protected $signature = 'ops:demand:classification:request';

    protected $description = 'Requests banner classification from classifiers';

    /** @var BannerClassificationCreator */
    private $creator;

    /** @var ClassifierExternalClient */
    private $client;

    /** @var ClassifierExternalRepository */
    private $classifierRepository;

    public function __construct(
        BannerClassificationCreator $creator,
        ClassifierExternalClient $client,
        ClassifierExternalRepository $classifierRepository,
        Locker $locker
    ) {
        $this->creator = $creator;
        $this->client = $client;
        $this->classifierRepository = $classifierRepository;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->signature.' already running');

            return;
        }

        $this->info('Start command '.$this->signature);

        $classifications = BannerClassification::fetchPendingForClassification();

        $this->info('[BannerClassificationRequest] number of requests to process: '.$classifications->count());

        $dataSet = $this->prepareData($classifications);
        $this->processData($dataSet);

        $this->info('Finish command '.$this->signature);
    }

    private function prepareData(Collection $classifications): array
    {
        $dataSet = [];

        /** @var BannerClassification $classification */
        foreach ($classifications as $classification) {
            $classifier = $classification->classifier;
            $banner = $classification->banner;
            $bannerPublicId = $banner->uuid;
            $campaign = $banner->campaign;

            $dataSet[$classifier]['banners'][] = $banner->id;
            $dataSet[$classifier]['requests'][] = [
                'id' => $bannerPublicId,
                'checksum' => $banner->creative_sha1,
                'type' => $banner->creative_type,
                'url' => route('banner-serve', ['id' => $bannerPublicId]),
                'campaign_id' => $campaign->uuid,
                'campaign_landing_url' => $campaign->landing_url,
            ];
        }

        return $dataSet;
    }

    private function processData(array $dataSet): void
    {
        foreach ($dataSet as $classifier => $data) {
            if (null === ($url = $this->classifierRepository->fetchClassifierUrl($classifier))) {
                $this->warn(
                    sprintf(
                        '[BannerClassificationRequest] unknown classifier (%s)',
                        $classifier
                    )
                );

                continue;
            }

            $bannerIds = $data['banners'];
            $requestData = [
                'callback_url' => route('demand-classifications-update', ['classifier' => $classifier]),
                'requests' => $data['requests'],
            ];

            BannerClassification::whereIn('banner_id', $bannerIds)->update(
                [
                    'status' => BannerClassification::STATUS_IN_PROGRESS,
                    'requested_at' => new DateTime(),
                ]
            );

            if (!$this->sendRequest($url, $requestData)) {
                BannerClassification::whereIn('banner_id', $bannerIds)->update(
                    [
                        'status' => BannerClassification::STATUS_ERROR,
                    ]
                );
            }
        }
    }

    private function sendRequest(string $url, array $data): bool
    {
        try {
            $this->client->requestClassification($url, $data);

            return true;
        } catch (RuntimeException $exception) {
            $this->info(
                sprintf(
                    '[BannerClassificationRequest] exception while sending request to classifier: %s [%s]',
                    $exception->getCode(),
                    $exception->getMessage()
                )
            );
        }

        return false;
    }
}

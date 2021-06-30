<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Client\ClassifierExternalClient;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Repository\Common\Dto\ClassifierExternal;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Support\Collection;

class BannerClassificationsRequestCommand extends BaseCommand
{
    protected $signature = 'ops:demand:classification:request';

    protected $description = 'Requests banner classification from classifiers';

    /** @var ClassifierExternalClient */
    private $client;

    /** @var ClassifierExternalRepository */
    private $classifierRepository;

    public function __construct(
        ClassifierExternalClient $client,
        ClassifierExternalRepository $classifierRepository,
        Locker $locker
    ) {
        $this->client = $client;
        $this->classifierRepository = $classifierRepository;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('Start command ' . $this->signature);

        $classifications = BannerClassification::fetchPendingForClassification();

        $this->info('[BannerClassificationRequest] number of requests to process: ' . $classifications->count());

        $dataSet = $this->prepareData($classifications);
        $this->processData($dataSet);

        $this->info('Finish command ' . $this->signature);
    }

    private function prepareData(Collection $classifications): array
    {
        $dataSet = [];

        /** @var BannerClassification $classification */
        foreach ($classifications as $classification) {
            $classifierName = $classification->classifier;
            $banner = $classification->banner;
            $bannerPublicId = $banner->uuid;
            $campaign = $banner->campaign;
            $checksum = $banner->creative_sha1;

            $dataSet[$classifierName]['ids'][] = $banner->id;
            $dataSet[$classifierName]['banners'][] = [
                'id' => $bannerPublicId,
                'checksum' => $checksum,
                'type' => $banner->creative_type,
                'size' => $banner->creative_size,
                'serve_url' => route('banner-serve', ['id' => $bannerPublicId, 'v' => substr($checksum, 0, 4)]),
                'campaign_id' => $campaign->uuid,
                'landing_url' => $campaign->landing_url,
            ];
        }

        return $dataSet;
    }

    private function processData(array $dataSet): void
    {
        foreach ($dataSet as $classifierName => $data) {
            if (null === ($classifier = $this->classifierRepository->fetchClassifierByName($classifierName))) {
                $this->warn(
                    sprintf(
                        '[BannerClassificationRequest] unknown classifier (%s)',
                        $classifierName
                    )
                );

                continue;
            }

            $bannerIds = $data['ids'];
            $requestData = [
                'callback_url' => route('demand-classifications-update', ['classifier' => $classifierName]),
                'banners' => $data['banners'],
            ];

            BannerClassification::setStatusInProgress($bannerIds);
            if (!$this->sendRequest($classifier, $requestData)) {
                BannerClassification::setStatusError($bannerIds);
            }
        }
    }

    private function sendRequest(ClassifierExternal $classifier, array $data): bool
    {
        try {
            $this->client->requestClassification($classifier, $data);

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

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

namespace Adshares\Adserver\Services\Demand;

use Adshares\Adserver\Http\Requests\Campaign\BannerValidator;
use Adshares\Adserver\Http\Requests\Campaign\CampaignTargetingProcessor;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CampaignCreator
{
    public function __construct(private readonly ConfigurationRepository $configurationRepository)
    {
    }

    /**
     * @param array $input
     * @return Campaign
     */
    public function prepareCampaignFromInput(array $input): Campaign
    {
        $medium = $input['medium'] ?? '';
        $vendor = $input['vendor'] ?? null;
        try {
            $mediumSchema = $this->configurationRepository->fetchMedium($medium, $vendor);
            $campaignTargetingProcessor = new CampaignTargetingProcessor($mediumSchema);
            $require = $campaignTargetingProcessor->processTargetingRequire($input['targeting']['requires'] ?? []);
            $exclude = $campaignTargetingProcessor->processTargetingExclude($input['targeting']['excludes'] ?? []);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        if (null === ($bidStrategy = BidStrategy::fetchDefault($medium, $vendor))) {
            Log::critical(sprintf('Bid strategy for (`%s`, `%s`) is missing', $medium, $vendor));
            throw new ServiceUnavailableHttpException();
        }

        //TODO validate
        $name = $input['name'];
        $status = $input['status'];
        $landingUrl = $input['target_url'];
        $budget = $input['budget'];
        $maxCpc = $input['max_cpc'];
        $maxCpm = $input['max_cpm'];
        $timeStart = $input['date_start'];
        $timeEnd = $input['date_end'] ?? null;

        return new Campaign([
            'landing_url' => $landingUrl,
            'name' => $name,
            'status' => $status,
            'budget' => $budget,
            'max_cpc' => $maxCpc,
            'max_cpm' => $maxCpm,
            'medium' => $medium,
            'vendor' => $vendor,
            'targeting_requires' => $require,
            'targeting_excludes' => $exclude,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'bid_strategy_uuid' => $bidStrategy->uuid,
        ]);
    }
}

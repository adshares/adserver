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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\TargetingReachRequest;
use Adshares\Adserver\Services\Advertiser\TargetingReachComputer;
use Adshares\Adserver\ViewModel\OptionsSelector;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class OptionsController extends Controller
{
    /** @var ConfigurationRepository */
    private $optionsRepository;

    public function __construct(ConfigurationRepository $optionsRepository)
    {
        $this->optionsRepository = $optionsRepository;
    }

    public function campaigns(): JsonResponse
    {
        return self::json(
            [
                'min_budget' => config('app.campaign_min_budget'),
                'min_cpm' => config('app.campaign_min_cpm'),
                'min_cpa' => config('app.campaign_min_cpa'),
            ]
        );
    }

    public function targeting(): JsonResponse
    {
        return self::json(new OptionsSelector($this->optionsRepository->fetchTargetingOptions()));
    }

    public function targetingReach(TargetingReachRequest $request): JsonResponse
    {
        $targeting = $request->toArray()['targeting'];

        $requires = AbstractFilterMapper::generateNestedStructure($targeting['requires']);
        $excludes = AbstractFilterMapper::generateNestedStructure($targeting['excludes']);

        $targetingReach = (new TargetingReachComputer())->compute($requires, $excludes);

        return self::json($targetingReach->toArray());
    }

    public function filtering(): JsonResponse
    {
        return self::json(new OptionsSelector($this->optionsRepository->fetchFilteringOptions()));
    }

    public function languages(): JsonResponse
    {
        return self::json(Simulator::getAvailableLanguages());
    }

    public function zones(): JsonResponse
    {
        return self::json(Simulator::getZoneTypes());
    }
}

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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Response\InfoResponse;
use Adshares\Adserver\Models\PanelPlaceholder;
use Adshares\Adserver\Repository\Common\MySqlServerStatisticsRepository;
use Adshares\Adserver\Repository\Common\TotalFeeReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InfoController extends Controller
{
    /** @var MySqlServerStatisticsRepository */
    private $adserverStatisticsRepository;

    /** @var TotalFeeReader */
    private $totalFeeReader;

    public function __construct(
        MySqlServerStatisticsRepository $adserverStatisticsRepository,
        TotalFeeReader $totalFeeReader
    ) {
        $this->adserverStatisticsRepository = $adserverStatisticsRepository;
        $this->totalFeeReader = $totalFeeReader;
    }

    public function info(): InfoResponse
    {
        $response = InfoResponse::defaults();

        $statistics = $this->adserverStatisticsRepository->fetchInfoStatistics();
        $response->updateWithStatistics($statistics);

        $response->updateWithDemandFee($this->totalFeeReader->getTotalFeeDemand());
        $response->updateWithSupplyFee($this->totalFeeReader->getTotalFeeSupply());

        return $response;
    }

    public function privacyPolicy(): View
    {
        return $this->regulation(PanelPlaceholder::TYPE_PRIVACY_POLICY);
    }

    public function terms(): View
    {
        return $this->regulation(PanelPlaceholder::TYPE_TERMS);
    }

    private function regulation(string $type): View
    {
        $regulation = PanelPlaceholder::fetchByType($type);
        $data = null === $regulation ? [] : $regulation->toArray();

        return view('info/policy', $data);
    }

    public function getPanelPlaceholders(Request $request): JsonResponse
    {
        $inputTypes = $request->input('types', PanelPlaceholder::TYPES_ALLOWED);

        if (!is_array($inputTypes)) {
            throw new BadRequestHttpException('Field types must be a non-empty array');
        }

        $types = array_intersect($inputTypes, PanelPlaceholder::TYPES_ALLOWED);

        if (count($types) !== count($inputTypes)) {
            throw new UnprocessableEntityHttpException('Invalid types');
        }

        $regulations = PanelPlaceholder::fetchByTypes($types)->keyBy(PanelPlaceholder::FIELD_TYPE)->toArray();

        return self::json($regulations);
    }
}

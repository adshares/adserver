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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Response\InfoResponse;
use Adshares\Adserver\Models\Regulation;
use Adshares\Adserver\Repository\Common\MySqlServerStatisticsRepository;
use Illuminate\View\View;

class InfoController extends Controller
{
    private $adserverStatisticsRepository;

    public function __construct(MySqlServerStatisticsRepository $adserverStatisticsRepository)
    {
        $this->adserverStatisticsRepository = $adserverStatisticsRepository;
    }

    public function info(): InfoResponse
    {
        $response = InfoResponse::defaults();

        $statistics = $this->adserverStatisticsRepository->fetchInfoStatistics();
        $response->updateWithStatistics($statistics);

        return $response;
    }

    public function privacyPolicy(): View
    {
        $data = Regulation::fetchPrivacyPolicy()->toArray();

        return view('info/policy', $data);
    }

    public function terms(): View
    {
        $data = Regulation::fetchTerms()->toArray();

        return view('info/policy', $data);
    }
}

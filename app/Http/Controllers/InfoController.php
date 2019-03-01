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
use Symfony\Component\HttpFoundation\JsonResponse;

class InfoController extends Controller
{
    public function info(): JsonResponse
    {
        $isAdvertiser = (bool)config('app.adserver_info_advertiser');
        $isPublisher = (bool)config('app.adserver_info_publisher');

        $supported = [];

        if ($isAdvertiser) {
            $supported[] = 'ADV';
        }

        if ($isPublisher) {
            $supported[] = 'PUB';
        }

        $data = [
            'type' => config('app.adserver_info_type'),
            'name' => config('app.adserver_info_name'),
            'version' => config('app.adserver_info_version'),
            'supported' => $supported,
            'panel-base-url' => config('app.adserver_info_panel_url'),
            'privacy-url' => config('app.adserver_info_privacy_url'),
            'terms-url' => config('app.adserver_info_terms_url'),
            'inventory-url' => route('demand-inventory'),
        ];

        return new JsonResponse($data);
    }
}

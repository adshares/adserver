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
use Adshares\Adserver\Utilities\ForceUrlProtocol;
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
            'serviceType' => config('app.adserver_info_type'),
            'name' => config('app.adserver_info_name'),
            'softwareVersion' => config('app.adserver_info_version'),
            'supported' => $supported,
            'serverUrl' => config('app.adserver_host'),
            'panelUrl' => config('app.adpanel_base_url'),
            'panel-base-url' => config('app.adpanel_base_url'),
            'privacyUrl' => config('app.adserver_info_privacy_url'),
            'termsUrl' => config('app.adserver_info_terms_url'),
            'inventoryUrl' => ForceUrlProtocol::change(route('demand-inventory')),
        ];

        return new JsonResponse($data);
    }
}

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
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Supply\Application\Dto\Info;
use Symfony\Component\HttpFoundation\JsonResponse;

class InfoController extends Controller
{
    public function info(): JsonResponse
    {
        $supported = [];

        if ((bool)config('app.adserver_info_advertiser')) {
            $supported[] = 'ADV';
        }

        if ((bool)config('app.adserver_info_publisher')) {
            $supported[] = 'PUB';
        }

        $info = new Info(
            (string)config('app.adserver_info_type'),
            (string)config('app.adserver_info_name'),
            (string)config('app.adserver_info_version'),
            new Url((string)config('app.adserver_host')),
            new Url((string)config('app.adpanel_base_url')),
            new Url((string)config('app.adserver_info_privacy_url')),
            new Url((string)config('app.adserver_info_terms_url')),
            new Url(ForceUrlProtocol::change(route('demand-inventory'))),
            ...$supported
        );

        //BC for Wordpress Plugin
        $data = $info->toArray();
        $data['panel-base-url'] = $data['panelUrl'];

        return new JsonResponse($data);
    }
}

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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\UpdateAdminSettings;
use Adshares\Adserver\Http\Requests\UpdateRegulation;
use Adshares\Adserver\Http\Response\SettingsResponse;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Regulation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    public function listSettings(): SettingsResponse
    {
        $settings = Config::fetchAdminSettings();

        return SettingsResponse::fromConfigModel($settings);
    }

    public function updateSettings(UpdateAdminSettings $request): JsonResponse
    {
        $input = $request->toConfigFormat();
        Config::updateAdminSettings($input);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    public function getLicence(): JsonResponse
    {
        // TODO replace mock with licence data
        $mockData = [
            'licence' => [
                'code' => 'ABCD-00000000-00000000-WXYZ',
                'organisation' => 'open source',
                'dateStart' => '2019-01-01 00:00:00',
                'dateEnd' => '2019-12-31 23:59:59',
                'serverUrl' => 'http://adshares.net',
            ],
        ];

        return new JsonResponse($mockData);
    }

    public function getTerms(): JsonResponse
    {
        return new JsonResponse(Regulation::fetchTerms());
    }

    public function putTerms(UpdateRegulation $request): JsonResponse
    {
        Regulation::addTerms($request->toString());

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    public function getPrivacyPolicy(): JsonResponse
    {
        return new JsonResponse(Regulation::fetchPrivacyPolicy());
    }

    public function putPrivacyPolicy(UpdateRegulation $request): JsonResponse
    {
        Regulation::addPrivacyPolicy($request->toString());

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}

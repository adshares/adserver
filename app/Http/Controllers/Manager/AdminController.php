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
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminController extends Controller
{
    /** @var LicenseVault */
    private $licenseVault;

    public function __construct(LicenseVault $licenseVault)
    {
        $this->licenseVault = $licenseVault;
    }

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

    public function getLicense(): JsonResponse
    {
        try {
            $license = $this->licenseVault->read();
        } catch (RuntimeException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        $licenseArray = $license->toArray();
        $licenseArray['detailsUrl'] = sprintf('%s/license/%s', config('app.license_url'), $licenseArray['id']);

        return new JsonResponse($licenseArray);
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

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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Response\LicenseResponse;
use Adshares\Adserver\Models\Config;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Exception\RuntimeException;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminController extends Controller
{
    public function getSettings(): JsonResponse
    {
        return self::json([
            'settings' => [
                'ad_user_info_url' => config('app.aduser_info_url'),
            ],
        ]);
    }

    public function getLicense(LicenseVault $licenseVault): LicenseResponse
    {
        try {
            $license = $licenseVault->read();
        } catch (RuntimeException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        return new LicenseResponse($license);
    }

    /**
     * @deprecated
     */
    public function getIndexUpdateTime(): JsonResponse
    {
        return self::json([
            'index_update_time' => Config::fetchDateTime(Config::PANEL_PLACEHOLDER_UPDATE_TIME)
                ->format(DateTimeInterface::ATOM),
        ]);
    }
}

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
use Adshares\Adserver\Models\User;
use Adshares\Advertiser\Dto\ChartInput as AdvertiserChartInput;
use Adshares\Advertiser\Dto\InvalidChartInputException;
use Adshares\Advertiser\Service\ChartDataProvider as AdvertiserChartDataProvider;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatsController extends Controller
{
    private const ADVERTISER = 'advertiser';
    private const PUBLISHER = 'publisher';

    /** @var AdvertiserChartDataProvider */
    private $advertiserChartDataProvider;

    public function __construct(AdvertiserChartDataProvider $advertiserChartDataProvider)
    {
        $this->advertiserChartDataProvider = $advertiserChartDataProvider;
    }

    public function chart(
        string $userType,
        string $type,
        string $resolution,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = DateTime::createFromFormat(DateTime::ATOM, $dateStart);
        $to = DateTime::createFromFormat(DateTime::ATOM, $dateEnd);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($user, $userType, $dateStart, $dateEnd);

        if ($userType === self::ADVERTISER && $user->isAdvertiser()) {
            try {
                $input = new AdvertiserChartInput($user->id, $type, $resolution, $from, $to);
            } catch (InvalidChartInputException $exception) {
                throw new BadRequestHttpException($exception->getMessage(), $exception);
            }

            $result = $this->advertiserChartDataProvider->fetch($input);

            return new JsonResponse($result->getData());
        }

        throw new AccessDeniedHttpException('Access denied.');
    }

    private function validateChartInputParameters(
        User $user,
        string $userType,
        string $dateStart,
        string $dateEnd
    ): void {
        if (!$dateStart) {
            throw new BadRequestHttpException('Bad format of start date.');
        }

        if (!$dateEnd) {
            throw new BadRequestHttpException('Bad format of end date.');
        }

        if (!$user) {
            throw new NotFoundHttpException('User is not found');
        }

        if ($userType === self::ADVERTISER && !$user->isAdvertiser()) {
            throw new AccessDeniedHttpException(sprintf(
                'User %s is not authorized to access this resource.',
                $user->email
            ));
        }

        if ($userType === self::PUBLISHER && !$user->isPublisher()) {
            throw new AccessDeniedHttpException(sprintf(
                'User %s is not authorized to access this resource.',
                $user->email
            ));
        }
    }
}

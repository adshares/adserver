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
use Adshares\Advertiser\Service\ChartProvider as AdvertiserChartProvider;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class StatsController extends Controller
{
    const ADVERTISER = 'advertiser';
    const PUBLISHER = 'publisher';

    /** @var AdvertiserChartProvider */
    private $advertiserChartProvider;

    public function __construct(AdvertiserChartProvider $advertiserChartProvider)
    {
        $this->advertiserChartProvider = $advertiserChartProvider;
    }

    public function chart(string $userType, string $type, string $resolution, string $dateStart, string $dateEnd)
    {
        $from = DateTime::createFromFormat(DateTime::ATOM, $dateStart);
        $to = DateTime::createFromFormat(DateTime::ATOM, $dateEnd);

        if (!$from) {
            throw new BadRequestHttpException(sprintf('Bad format of start date `%s`.', $dateStart));
        }

        if (!$to) {
            throw new BadRequestHttpException(sprintf('Bad format of end date `%s`.', $dateEnd));
        }

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($user, $userType, $from, $to);

        if ($user->isAdvertiser()) {
            $input = new AdvertiserChartInput($user->id, $type, $resolution, $from, $to);
            $result = $this->advertiserChartProvider->fetch($input);

            return new JsonResponse($result->getData());
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    private function validateChartInputParameters(User $user, string $userType, DateTime $dateStart, DateTime $dateEnd)
    {
        if (!$user) {
            throw new NotFoundHttpException('User is not found');
        }

        if ($userType === self::ADVERTISER && !$user->isAdvertiser()) {
            throw new UnauthorizedHttpException(
                '',
                sprintf(
                    'User %s is not authorized to access this resource.',
                    $user->email
                ));
        }

        if ($userType === self::PUBLISHER && !$user->isPublisher()) {
            throw new UnauthorizedHttpException(
                '',
                sprintf(
                    'User %s is not authorized to access this resource.',
                    $user->email
                ));
        }
    }
}

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
use Adshares\Adserver\Http\Response\Stats\AdvertiserReportResponse;
use Adshares\Adserver\Http\Response\Stats\PublisherReportResponse;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Advertiser\Dto\Input\ChartInput as AdvertiserChartInput;
use Adshares\Advertiser\Dto\Input\ConversionDataInput;
use Adshares\Advertiser\Dto\Input\InvalidInputException as AdvertiserInvalidInputException;
use Adshares\Advertiser\Dto\Input\StatsInput as AdvertiserStatsInput;
use Adshares\Advertiser\Service\ChartDataProvider as AdvertiserChartDataProvider;
use Adshares\Advertiser\Service\StatsDataProvider as AdvertiserStatsDataProvider;
use Adshares\Publisher\Dto\Input\ChartInput as PublisherChartInput;
use Adshares\Publisher\Dto\Input\InvalidInputException as PublisherInvalidInputException;
use Adshares\Publisher\Dto\Input\StatsInput as PublisherStatsInput;
use Adshares\Publisher\Service\ChartDataProvider as PublisherChartDataProvider;
use Adshares\Publisher\Service\StatsDataProvider as PublisherStatsDataProvider;
use DateTime;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatsController extends Controller
{
    /** @var AdvertiserChartDataProvider */
    private $advertiserChartDataProvider;

    /** @var AdvertiserStatsDataProvider */
    private $advertiserStatsDataProvider;

    /** @var PublisherChartDataProvider */
    private $publisherChartDataProvider;

    /** @var PublisherStatsDataProvider */
    private $publisherStatsDataProvider;

    public function __construct(
        AdvertiserChartDataProvider $advertiserChartDataProvider,
        AdvertiserStatsDataProvider $advertiserStatsDataProvider,
        PublisherChartDataProvider $publisherChartDataProvider,
        PublisherStatsDataProvider $publisherStatsDataProvider
    ) {
        $this->advertiserChartDataProvider = $advertiserChartDataProvider;
        $this->advertiserStatsDataProvider = $advertiserStatsDataProvider;
        $this->publisherChartDataProvider = $publisherChartDataProvider;
        $this->publisherStatsDataProvider = $publisherStatsDataProvider;
    }

    public function advertiserChart(
        Request $request,
        string $type,
        string $resolution,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignUuid = $this->getCampaignFromRequest($request)->uuid ?? null;

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsAdvertiser($user);

        try {
            $input = new AdvertiserChartInput(
                $user->uuid,
                $type,
                $resolution,
                $from,
                $to,
                $campaignUuid
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserChartDataProvider->fetch($input);

        return new JsonResponse($result->toArray());
    }

    public function publisherChart(
        Request $request,
        string $type,
        string $resolution,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $siteId = $this->getSiteIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsPublisher($user);

        try {
            $input = new PublisherChartInput(
                $user->uuid,
                $type,
                $resolution,
                $from,
                $to,
                $siteId
            );
        } catch (PublisherInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->publisherChartDataProvider->fetch($input);

        return new JsonResponse($result->toArray());
    }

    private function createDateTime(string $dateInISO8601Format): ?DateTime
    {
        $date = DateTime::createFromFormat(DateTime::ATOM, $dateInISO8601Format);

        if (!$date) {
            return null;
        }

        return $date;
    }

    private function getCampaignFromRequest(Request $request): ?Campaign
    {
        $campaignId = $request->input('campaign_id');

        if (!$campaignId) {
            return null;
        }

        $campaign = Campaign::find($campaignId);

        if (!$campaign) {
            throw new NotFoundHttpException('Campaign does not exists.');
        }

        return $campaign;
    }

    private function validateChartInputParameters(
        ?DateTime $dateStart,
        ?DateTime $dateEnd
    ): void {
        if (!$dateStart) {
            throw new BadRequestHttpException('Bad format of start date.');
        }

        if (!$dateEnd) {
            throw new BadRequestHttpException('Bad format of end date.');
        }
    }

    public function advertiserStatsWithTotal(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignUuid = $this->getCampaignFromRequest($request)->uuid ?? null;

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsAdvertiser($user);

        try {
            $input = new AdvertiserStatsInput(
                $user->uuid,
                $from,
                $to,
                $campaignUuid
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserStatsDataProvider->fetch($input);

        return new JsonResponse(
            [
                'total' => $result->getTotal(),
                'data' => $result->getData(),
            ]
        );
    }

    public function advertiserStatsConversions(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignId = $this->getCampaignFromRequest($request)->id ?? null;

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsAdvertiser($user);

        try {
            $input = new ConversionDataInput(
                $user->id,
                $from,
                $to,
                $campaignId
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserStatsDataProvider->fetchConversionData($input);

        return self::json($result->toArray());
    }

    public function publisherReport(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): StreamedResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $siteId = $this->getSiteIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $this->validateChartInputParameters($from, $to);
        if (!$isAdmin) {
            $this->validateUserAsPublisher($user);
        }

        try {
            $input = new PublisherStatsInput(
                $isAdmin ? null : $user->uuid,
                $from,
                $to,
                $siteId
            );
        } catch (PublisherInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->publisherStatsDataProvider->fetchReportData($input);

        $data = $result->toArray();
        $name = $this->formatReportName($from, $to);

        return (new PublisherReportResponse($data, $name, (string)config('app.name'), $isAdmin))->responseStream();
    }

    public function advertiserReport(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): StreamedResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignUuid = $this->getCampaignFromRequest($request)->uuid ?? null;

        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $this->validateChartInputParameters($from, $to);
        if (!$isAdmin) {
            $this->validateUserAsAdvertiser($user);
        }

        try {
            $input = new AdvertiserStatsInput(
                $isAdmin ? null : $user->uuid,
                $from,
                $to,
                $campaignUuid
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserStatsDataProvider->fetchReportData($input);

        $data = $result->toArray();
        $name = $this->formatReportName($from, $to);

        return (new AdvertiserReportResponse($data, $name, (string)config('app.name'), $isAdmin))->responseStream();
    }

    public function advertiserReportFile(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): BinaryFileResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignUuid = $this->getCampaignFromRequest($request)->uuid ?? null;

        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $this->validateChartInputParameters($from, $to);
        if (!$isAdmin) {
            $this->validateUserAsAdvertiser($user);
        }

        try {
            $input = new AdvertiserStatsInput(
                $isAdmin ? null : $user->uuid,
                $from,
                $to,
                $campaignUuid
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserStatsDataProvider->fetchReportData($input);

        $data = $result->toArray();
        $name = $this->formatReportName($from, $to);

        return (new AdvertiserReportResponse($data, $name, (string)config('app.name'), $isAdmin))->responseFile();
    }

    public function publisherStatsWithTotal(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $siteId = $this->getSiteIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsPublisher($user);

        try {
            $input = new PublisherStatsInput(
                $user->uuid,
                $from,
                $to,
                $siteId
            );
        } catch (PublisherInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->publisherStatsDataProvider->fetch($input);

        return new JsonResponse(
            [
                'total' => $result->getTotal(),
                'data' => $result->getData(),
            ]
        );
    }

    private function getSiteIdFromRequest(Request $request): ?string
    {
        $siteId = $request->input('site_id');

        if (!$siteId) {
            return null;
        }

        $site = Site::find($siteId);

        if (!$site) {
            throw new NotFoundHttpException('Site does not exists.');
        }

        return $site->uuid;
    }

    private function validateUserAsPublisher(User $user): void
    {
        if (!$user->isPublisher()) {
            throw new AccessDeniedHttpException(
                sprintf(
                    'User %s is not authorized to access this resource.',
                    $user->email
                )
            );
        }
    }

    private function validateUserAsAdvertiser(User $user): void
    {
        if (!$user->isAdvertiser()) {
            throw new AccessDeniedHttpException(
                sprintf(
                    'User %s is not authorized to access this resource.',
                    $user->email
                )
            );
        }
    }

    private function formatReportName(DateTimeInterface $from, DateTimeInterface $to): string
    {
        return sprintf(
            '%s_%s',
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );
    }
}

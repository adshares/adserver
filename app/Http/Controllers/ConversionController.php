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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\ConversionGroup;
use Adshares\Adserver\Models\EventConversionLog;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\ConversionValidator;
use Adshares\Adserver\Services\EventCaseFinder;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function base64_decode;
use function bin2hex;
use function inet_pton;
use function sprintf;

class ConversionController extends Controller
{
    private const ONE_PIXEL_GIF_DATA = 'R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

    /** @var CampaignRepository */
    private $campaignRepository;

    /** @var ConversionValidator */
    private $conversionValidator;

    /** @var EventCaseFinder */
    private $eventCaseFinder;

    public function __construct(
        CampaignRepository $campaignRepository,
        ConversionValidator $conversionValidator,
        EventCaseFinder $eventCaseFinder
    ) {
        $this->campaignRepository = $campaignRepository;
        $this->conversionValidator = $conversionValidator;
        $this->eventCaseFinder = $eventCaseFinder;
    }

    public function conversion(string $uuid, Request $request): JsonResponse
    {
        $this->validateUuid($uuid);

        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrlNext = $this->selectNextBaseUrl($request);

            if (null === $baseUrlNext) {
                throw new BadRequestHttpException('Missing case id');
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['uuid' => $uuid], false));
        }

        $response = self::json(['status' => 'OK'], Response::HTTP_OK);

        $this->processConversion($uuid, $request, $response);

        return $response;
    }

    public function conversionGif(string $uuid, Request $request): Response
    {
        $this->validateUuid($uuid);

        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrlNext = $this->selectNextBaseUrl($request);

            if (null === $baseUrlNext) {
                Log::info(
                    sprintf('[DemandController] conversion error 400 (Missing case id for: %s)', $uuid)
                );

                return $this->createOnePixelResponse();
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['uuid' => $uuid], false));
        }

        $response = $this->createOnePixelResponse();
        $response->send();

        try {
            $this->processConversion($uuid, $request);
        } catch (BadRequestHttpException|NotFoundHttpException $exception) {
            Log::info(
                sprintf(
                    '[DemandController] conversion error %d (%s)',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                )
            );
        }

        return $response;
    }

    public function conversionClick(string $campaignUuid, Request $request): JsonResponse
    {
        $this->validateUuid($campaignUuid);

        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrlNext = $this->selectNextBaseUrl($request);

            if (null === $baseUrlNext) {
                throw new BadRequestHttpException('Missing case id');
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['campaign_uuid' => $campaignUuid], false));
        }

        $response = self::json(['status' => 'OK'], Response::HTTP_OK);

        $this->processConversionClick($campaignUuid, $request, $response);

        return $response;
    }

    public function conversionClickGif(string $campaignUuid, Request $request): Response
    {
        $this->validateUuid($campaignUuid);

        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrlNext = $this->selectNextBaseUrl($request);

            if (null === $baseUrlNext) {
                Log::info(
                    sprintf('[DemandController] conversion error 400 (Missing case id for campaign: %s)', $campaignUuid)
                );

                return $this->createOnePixelResponse();
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['campaign_uuid' => $campaignUuid], false));
        }

        $response = $this->createOnePixelResponse();
        $response->send();

        try {
            $this->processConversionClick($campaignUuid, $request);
        } catch (BadRequestHttpException|NotFoundHttpException $exception) {
            Log::info(
                sprintf(
                    '[DemandController] click conversion error %d (%s)',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                )
            );
        }

        return $response;
    }

    private function validateConversionAdvanced(Request $request, string $secret, string $conversionUuid): void
    {
        $signature = $request->input('sig');
        if (null === $signature) {
            throw new BadRequestHttpException(
                sprintf('No signature provided for: %s', $conversionUuid)
            );
        }

        $nonce = $request->input('nonce');
        if (null === $nonce) {
            throw new BadRequestHttpException(
                sprintf('No nonce provided for: %s', $conversionUuid)
            );
        }

        $timestampCreated = $request->input('ts');
        if (null === $timestampCreated) {
            throw new BadRequestHttpException(
                sprintf('No timestamp provided for: %s', $conversionUuid)
            );
        }

        $timestampCreated = (int)$timestampCreated;
        if ($timestampCreated <= 0) {
            throw new BadRequestHttpException(
                sprintf('Invalid timestamp for: %s', $conversionUuid)
            );
        }

        $value = $request->input('value', '');

        try {
            $isSignatureValid = $this->conversionValidator->validateSignature(
                $signature,
                $conversionUuid,
                $nonce,
                $timestampCreated,
                $value,
                $secret
            );
        } catch (RuntimeException $exception) {
            Log::info(
                sprintf(
                    '[DemandController] Conversion signature error: (%s) for: %s',
                    $exception->getMessage(),
                    $conversionUuid
                )
            );

            $isSignatureValid = false;
        }

        if (!$isSignatureValid) {
            throw new BadRequestHttpException(
                sprintf('Invalid signature for: %s', $conversionUuid)
            );
        }
    }

    private function processConversion(string $uuid, Request $request, Response $response = null): void
    {
        $conversionDefinition = $this->fetchConversionDefinitionOrFail($uuid);

        $isAdvanced = $conversionDefinition->isAdvanced();

        $value = $this->getConversionValue($request, $conversionDefinition);

        $campaign = Campaign::find($conversionDefinition->campaign_id);

        if ($isAdvanced) {
            $secret = $campaign->secret;

            $this->validateConversionAdvanced($request, $secret, $uuid);
        }

        $conversionDefinitionId = $conversionDefinition->id;
        $campaignPublicId = $campaign->uuid;

        $cases = $this->findCasesConnectedWithConversion($request, $campaignPublicId);

        if (!$conversionDefinition->isRepeatable()) {
            $caseIds = array_keys($cases);

            if (ConversionGroup::containsConversionMatchingCaseIds($conversionDefinitionId, $caseIds)) {
                throw new BadRequestHttpException(
                    sprintf('Repeated conversion: %s', $uuid)
                );
            }
        }

        if (null !== $response) {
            $response->send();
        }

        $advertiserId = $campaign->user->uuid;
        $groupId = Uuid::v4()->toString();
        $headers = $request->headers->all();
        $ip = bin2hex(inet_pton($request->getClientIp()));
        $impressionContext = Utils::getImpressionContextArray($request);

        $viewEventsData = $this->getViewEventsData($cases);

        DB::beginTransaction();

        foreach ($cases as $caseId => $weight) {
            $viewEventData = $viewEventsData[$caseId];

            $event = EventConversionLog::createWithUserData(
                $caseId,
                Uuid::v4()->toString(),
                $viewEventData['bannerId'],
                $viewEventData['zoneId'],
                $viewEventData['trackingId'],
                $viewEventData['publisherId'],
                $campaignPublicId,
                $advertiserId,
                $viewEventData['payTo'],
                $ip,
                $headers,
                $impressionContext,
                '',
                EventLog::TYPE_CONVERSION,
                $viewEventData['humanScore'],
                $viewEventData['ourUserdata']
            );

            $eventId = $event->id;
            $partialValue = (int)floor($value * $weight);
            ConversionGroup::register($caseId, $groupId, $eventId, $conversionDefinitionId, $partialValue, $weight);
        }

        DB::commit();
    }

    private function processConversionClick(string $campaignUuid, Request $request, Response $response = null): void
    {
        $campaign = Campaign::fetchByUuid($campaignUuid);

        if (null === $campaign) {
            throw new NotFoundHttpException(
                sprintf('No matching campaign found for id: %s', $campaignUuid)
            );
        }

        if (!$campaign->hasClickConversion()) {
            throw new BadRequestHttpException(
                sprintf('Click conversion not supported for campaign: %s', $campaignUuid)
            );
        }

        if ($campaign->hasClickConversionAdvanced()) {
            $secret = $campaign->secret;

            $this->validateConversionAdvanced($request, $secret, $campaignUuid);
        }

        $campaignPublicId = $campaign->uuid;

        $cases = $this->findCasesConnectedWithConversion($request, $campaignPublicId);

        if (null !== $response) {
            $response->send();
        }

        $advertiserId = $campaign->user->uuid;
        $headers = $request->headers->all();
        $ip = bin2hex(inet_pton($request->getClientIp()));
        $impressionContext = Utils::getImpressionContextArray($request);

        $viewEventsData = $this->getViewEventsData($cases);

        DB::beginTransaction();

        foreach ($cases as $caseId => $weight) {
            $viewEventData = $viewEventsData[$caseId];

            if (EventLog::eventClicked($caseId) > 0) {
                EventLog::createWithUserData(
                    $caseId,
                    Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_CLICK),
                    $viewEventData['bannerId'],
                    $viewEventData['zoneId'],
                    $viewEventData['trackingId'],
                    $viewEventData['publisherId'],
                    $campaignPublicId,
                    $advertiserId,
                    $viewEventData['payTo'],
                    $ip,
                    $headers,
                    $impressionContext,
                    '',
                    EventLog::TYPE_CLICK,
                    $viewEventData['humanScore'],
                    $viewEventData['ourUserdata']
                );
            }
        }

        DB::commit();
    }

    private function fetchConversionDefinitionOrFail(string $uuid): ConversionDefinition
    {
        $conversionDefinition = ConversionDefinition::fetchByUuid($uuid);
        if (!$conversionDefinition) {
            throw new NotFoundHttpException(
                sprintf('No conversion found for id: %s', $uuid)
            );
        }

        return $conversionDefinition;
    }

    private function getConversionValue(Request $request, ConversionDefinition $conversionDefinition): int
    {
        if (!$conversionDefinition->isAdvanced()) {
            return $conversionDefinition->value;
        }

        if ($request->has('value')) {
            $value = is_numeric($request->input('value')) ? $request->input('value') * 10 ** 11 : null;
        } else {
            $value = $conversionDefinition->value;
        }
        if (null === $value) {
            throw new BadRequestHttpException(
                sprintf('No value provided for: %s', $conversionDefinition->uuid)
            );
        }

        $value = (int)$value;
        if ($value <= 0) {
            throw new BadRequestHttpException(
                sprintf('Invalid value of %d for: %s', $value, $conversionDefinition->uuid)
            );
        }

        return $value;
    }

    private function findCasesConnectedWithConversion(Request $request, string $campaignPublicId): array
    {
        $cid = $request->input('cid');
        if (null !== $cid) {
            $results = $this->eventCaseFinder->findByCaseId($campaignPublicId, $cid);
        } else {
            $tid = $request->cookies->get('tid') ? Utils::hexUuidFromBase64UrlWithChecksum(
                $request->cookies->get('tid')
            ) : null;

            if (null === $tid) {
                throw new BadRequestHttpException(
                    sprintf('Missing case id for campaign: %s', $campaignPublicId)
                );
            }

            $results = $this->eventCaseFinder->findByTrackingId($campaignPublicId, $tid);
        }

        if (0 === count($results)) {
            throw new NotFoundHttpException(
                sprintf('No matching case found for campaign: %s', $campaignPublicId)
            );
        }

        return $results;
    }

    private function getViewEventsData(array $cases): array
    {
        $viewEventsData = [];
        $eventPublicIds = [];
        foreach ($cases as $caseId => $weight) {
            $eventPublicIds[] = Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_VIEW);
        }
        $viewEvents = EventLog::fetchByEventIds($eventPublicIds);

        foreach ($viewEvents as $viewEvent) {
            /** @var $viewEvent EventLog */
            $viewEventsData[$viewEvent->case_id] = [
                'bannerId' => $viewEvent->banner_id,
                'zoneId' => $viewEvent->zone_id,
                'trackingId' => $viewEvent->tracking_id,
                'publisherId' => $viewEvent->publisher_id,
                'payTo' => $viewEvent->pay_to,
                'humanScore' => null !== $viewEvent->human_score ? (float)$viewEvent->human_score : null,
                'ourUserdata' => $viewEvent->our_userdata,
            ];
        }

        return $viewEventsData;
    }

    private function selectNextBaseUrl(Request $request): ?string
    {
        $urls = ServeDomain::fetch();

        $baseUrl = (new SecureUrl($request->getSchemeAndHttpHost()))->toString();

        if (false === ($key = array_search($baseUrl, $urls, true))) {
            return array_shift($urls);
        }

        return $urls[$key + 1] ?? null;
    }

    private function validateUuid(string $uuid): void
    {
        if (32 !== strlen($uuid)) {
            throw new BadRequestHttpException(
                sprintf('Invalid id: %s', $uuid)
            );
        }
    }

    private function createOnePixelResponse(): Response
    {
        $response = new Response(base64_decode(self::ONE_PIXEL_GIF_DATA));
        $response->headers->set('Content-Type', 'image/gif');

        return $response;
    }
}

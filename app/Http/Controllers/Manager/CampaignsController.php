<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\Campaign\CampaignTargetingProcessor;
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Filter\FilterType;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Common\CrmNotifier;
use Adshares\Adserver\Services\Demand\BannerCreator;
use Adshares\Adserver\Uploader\Factory;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class CampaignsController extends Controller
{
    public function __construct(
        private readonly BannerCreator $bannerCreator,
        private readonly CampaignRepository $campaignRepository,
        private readonly ConfigurationRepository $configurationRepository,
        private readonly ExchangeRateReader $exchangeRateReader,
    ) {
    }

    public function upload(Request $request): UploadedFile
    {
        $mediumName = $request->get('medium');
        $vendor = $request->get('vendor');
        $type = $request->get('type');
        if (!is_string($mediumName)) {
            throw new UnprocessableEntityHttpException('Field `medium` must be a string');
        }
        if (null !== $vendor && !is_string($vendor)) {
            throw new UnprocessableEntityHttpException('Field `vendor` must be a string or null');
        }
        if (!is_string($type)) {
            throw new UnprocessableEntityHttpException('Field `type` must be a string');
        }
        try {
            $medium = $this->configurationRepository->fetchMedium($mediumName, $vendor);
            return Factory::createFromType($type, $request)->upload($medium);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }
    }

    public function uploadPreview(Request $request, string $type, string $uuid): Response
    {
        try {
            $uuidObject = Uuid::fromString($uuid);
        } catch (InvalidUuidStringException) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid ID %s', $uuid));
        }
        return Factory::createFromType($type, $request)->preview($uuidObject);
    }

    public function preview($bannerPublicId): Response
    {
        $banner = Banner::fetchBanner((string)$bannerPublicId);

        if (!$banner || empty($banner->creative_contents)) {
            throw new NotFoundHttpException(sprintf('Banner %s does not exist.', $banner));
        }

        $response = ResponseFacade::make($banner->creative_contents, Response::HTTP_OK);
        $response->header('Content-Type', $banner->creative_mime);

        return $response;
    }

    public function add(Request $request): JsonResponse
    {
        $exchangeRate = $this->fetchExchangeRateOrFail();

        $input = $request->input('campaign');
        $this->validateCampaignInput($input);
        try {
            $input = $this->processTargeting($input);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }
        $bidStrategy = BidStrategy::fetchDefault(
            $input['basic_information']['medium'] ?? '',
            $input['basic_information']['vendor'] ?? null
        );
        if (null === $bidStrategy) {
            throw new ServiceUnavailableHttpException();
        }
        $status = $input['basic_information']['status'];

        $input['basic_information']['status'] = Campaign::STATUS_DRAFT;
        /** @var User $user */
        $user = Auth::user();
        $input['user_id'] = $user->id;
        $input['bid_strategy_uuid'] = $bidStrategy->uuid;

        $campaign = new Campaign($input);

        $banners = $conversions = [];
        if (isset($input['ads']) && is_array($input['ads']) && count($input['ads']) > 0) {
            try {
                $banners = $this->bannerCreator->prepareBannersFromInput($input['ads'], $campaign);
            } catch (InvalidArgumentException $exception) {
                throw new UnprocessableEntityHttpException($exception->getMessage());
            }
        }
        if (isset($input['conversions']) && count($input['conversions']) > 0) {
            foreach ($this->prepareConversionsFromInput($input['conversions']) as $conversionInput) {
                $conversion = new ConversionDefinition();
                $conversion->fill($conversionInput);

                $conversions[] = $conversion;
            }
        }

        $this->campaignRepository->save($campaign, $banners, $conversions);

        $this->removeTemporaryUploadedFiles((array)$input['ads'], $request);

        if ($campaign->changeStatus($status, $exchangeRate)) {
            $this->campaignRepository->update($campaign);
        }

        CrmNotifier::sendCrmMailOnCampaignCreated($user, $campaign);

        return self::json($campaign->toArray(), Response::HTTP_CREATED)->header(
            'Location',
            route('app.campaigns.read', ['campaign_id' => $campaign->id])
        );
    }

    private function removeTemporaryUploadedFiles(array $files, Request $request): void
    {
        foreach ($files as $file) {
            if (!isset($file['uuid']) && isset($file['creative_type']) && isset($file['url'])) {
                Factory::createFromType($file['creative_type'], $request)
                    ->removeTemporaryFile(Uuid::fromString(Utils::extractFilename($file['url'])));
            }
        }
    }

    private function prepareConversionsFromInput(array $input): array
    {
        $this->validateConversions($input);

        return $input;
    }

    public function browse(Request $request): JsonResponse
    {
        $filters = FilterCollection::fromRequest($request, [
            'medium' => FilterType::String,
            'vendor' => FilterType::String,
        ]);
        $campaigns = $this->campaignRepository->find($filters);

        foreach ($campaigns as $campaign) {
            $campaign->classifications = BannerClassification::fetchCampaignClassifications($campaign->id);
        }

        return self::json($campaigns);
    }

    public function edit(Request $request, int $campaignId): JsonResponse
    {
        $exchangeRate = $this->fetchExchangeRateOrFail();
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        $input = $request->input('campaign');
        $this->validateCampaignInput($input);
        try {
            $input = $this->processTargeting($input);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        unset($input['status']); // Client cannot change status in EDIT action

        $ads = $request->input('campaign.ads');
        $banners = Collection::make($ads);

        unset($input['bid_strategy_uuid']);
        if (array_key_exists('bid_strategy', $input)) {
            $bidStrategyUuid = $input['bid_strategy']['uuid'] ?? null;
            if (!Utils::isUuidValid($bidStrategyUuid)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid bid strategy id (%s)', $bidStrategyUuid));
            }
            $input['bid_strategy_uuid'] = $bidStrategyUuid;
        }
        $conversions = $this->prepareConversionsFromInput($input['conversions'] ?? []);

        if ($input['basic_information']['medium'] !== $campaign->medium) {
            throw new UnprocessableEntityHttpException('Medium cannot be changed');
        }
        if ($input['basic_information']['vendor'] !== $campaign->vendor) {
            throw new UnprocessableEntityHttpException('Vendor cannot be changed');
        }
        $status = $campaign->status;
        $campaign->fill($input);
        $campaign->status = Campaign::STATUS_INACTIVE;

        $bannersToUpdate = [];
        $bannersToDelete = [];
        $bannersToInsert = [];

        foreach ($campaign->banners as $banner) {
            $bannerFromInput = $banners->firstWhere('uuid', $banner->uuid);

            if ($bannerFromInput) {
                $banner->name = $bannerFromInput['name'] ?? $bannerFromInput['creative_size'];
                if ($banner->creative_type === Banner::TEXT_TYPE_DIRECT_LINK) {
                    $banner->creative_contents = Utils::appendFragment(
                        empty($bannerFromInput['creative_contents']) ? $campaign->landing_url
                            : $bannerFromInput['creative_contents'],
                        $banner->creative_size
                    );
                }
                $bannersToUpdate[] = $banner;

                $banners = $banners->reject(
                    function ($value) use ($banner) {
                        return (string)($value['uuid'] ?? '') === $banner->uuid;
                    }
                );

                continue;
            }

            $bannersToDelete[] = $banner;
        }

        if ($banners) {
            try {
                $bannersToInsert = $this->bannerCreator->prepareBannersFromInput($banners->toArray(), $campaign);
            } catch (InvalidArgumentException $exception) {
                throw new UnprocessableEntityHttpException($exception->getMessage());
            }
        }

        $conversionsToInsert = [];
        $conversionsToUpdate = [];
        $dbConversions = $campaign->conversions->keyBy('uuid');

        foreach ($conversions as $conversionInput) {
            if (!isset($conversionInput['uuid'])) {
                $conversion = new ConversionDefinition();
                $conversion->fill($conversionInput);

                $conversionsToInsert[] = $conversion;

                continue;
            }

            if (isset($dbConversions[$conversionInput['uuid']])) {
                /** @var ConversionDefinition $conversion */
                $conversion = $dbConversions[$conversionInput['uuid']];

                if ($conversionInput['type'] === $conversion->type) {
                    $conversion->name = $conversionInput['name'];
                    $conversion->limit_type = $conversionInput['limit_type'];
                    $conversion->event_type = $conversionInput['event_type'];
                    $conversion->value = $conversionInput['value'];
                    $conversion->is_value_mutable = $conversionInput['is_value_mutable'];
                    $conversion->is_repeatable = $conversionInput['is_repeatable'];

                    $conversionsToUpdate[] = $conversion;
                }

                unset($dbConversions[$conversionInput['uuid']]);
            }
        }

        $conversionUuidsToDelete = $dbConversions->keys()->all();

        try {
            $this->campaignRepository->update(
                $campaign,
                $bannersToInsert,
                $bannersToUpdate,
                $bannersToDelete,
                $conversionsToInsert,
                $conversionsToUpdate,
                $conversionUuidsToDelete,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        if ($ads) {
            $this->removeTemporaryUploadedFiles($ads, $request);
        }

        if ($campaign->changeStatus($status, $exchangeRate)) {
            $this->campaignRepository->update($campaign);
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    private function validateConversions(array $conversions): void
    {
        foreach ($conversions as $conversion) {
            $validator = Validator::make($conversion, ConversionDefinition::rules($conversion));

            if ($validator->fails()) {
                $errors = $validator->errors()->toArray();
                throw new HttpResponseException(
                    response()->json(
                        [
                            'errors' => $errors,
                        ],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    )
                );
            }
        }
    }

    public function changeStatus(Campaign $campaign, Request $request): JsonResponse
    {
        if (!$request->has('campaign.status')) {
            throw new UnprocessableEntityHttpException('No status provided');
        }

        $campaign->status = (int)$request->input('campaign.status');

        return $this->changeCampaignStatus($campaign);
    }

    private function changeCampaignStatus(Campaign $campaign): JsonResponse
    {
        try {
            $this->campaignRepository->update($campaign);
        } catch (InvalidArgumentException) {
            throw new UnprocessableEntityHttpException(sprintf('Cannot set status to {%d}', $campaign->status));
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function activateOutdatedCampaign(Campaign $campaign): JsonResponse
    {
        $campaign->time_end = null;
        $campaign->status = Campaign::STATUS_ACTIVE;

        return $this->changeCampaignStatus($campaign);
    }

    public function changeBannerStatus(Request $request, int $campaignId, int $bannerId): JsonResponse
    {
        $status = (int)$request->input('banner.status');

        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        /** @var Banner $banner */
        $banner = $campaign->banners()->where('id', $bannerId)->first();

        try {
            $banner = $this->bannerCreator->updateBanner(['status' => $status], $banner);
            $this->campaignRepository->update($campaign, bannersToUpdate: [$banner]);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function delete(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $this->campaignRepository->delete($campaign);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function read(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignByIdWithConversions($campaignId);
        $campaign->classifications = BannerClassification::fetchCampaignClassifications($campaign->id);

        return self::json(['campaign' => $campaign->toArray()]);
    }

    public function clone(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        DB::beginTransaction();
        try {
            $clonedCampaign = $campaign->replicate();
            $clonedCampaign->status = Campaign::STATUS_DRAFT;
            $clonedCampaign->name = sprintf('%s (Cloned)', $campaign->name);
            $clonedCampaign->saveOrFail();

            foreach ($campaign->conversions as $conversion) {
                $clonedConversion = $conversion->replicate();
                $clonedConversion->campaign_id = $clonedCampaign->id;
                $clonedConversion->cost = 0;
                $clonedConversion->occurrences = 0;
                $clonedConversion->saveOrFail();
            }

            $campaign->bannersWithContent()->chunk(1, function ($chunk) use ($clonedCampaign) {
                $banner = $chunk->first();
                $clonedBanner = $banner->replicate();
                $clonedBanner->campaign_id = $clonedCampaign->id;
                $clonedBanner->saveOrFail();
            });
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Exception during cloning campaign (%s)', $throwable->getMessage()));
            throw $throwable;
        }

        return self::json($clonedCampaign->toArray(), Response::HTTP_CREATED)->header(
            'Location',
            route('app.campaigns.read', ['campaign_id' => $clonedCampaign->id])
        );
    }

    private function processTargeting(array $input): array
    {
        $medium = $input['basic_information']['medium'] ?? '';
        $vendor = $input['basic_information']['vendor'] ?? null;
        $campaignTargetingProcessor = new CampaignTargetingProcessor(
            $this->configurationRepository->fetchMedium($medium, $vendor)
        );
        $input['targeting_requires'] = $campaignTargetingProcessor->processTargetingRequire(
            $input['targeting']['requires'] ?? []
        );
        $input['targeting_excludes'] = $campaignTargetingProcessor->processTargetingExclude(
            $input['targeting']['excludes'] ?? []
        );

        return $input;
    }

    private function fetchExchangeRateOrFail(): ExchangeRate
    {
        if (Currency::ADS !== Currency::from(config('app.currency'))) {
            return ExchangeRate::ONE(Currency::ADS);
        }

        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();
        } catch (ExchangeRateNotAvailableException $exception) {
            Log::error(sprintf('Exchange rate is not available (%s)', $exception->getMessage()));
            throw new ServiceUnavailableHttpException();
        }
        return $exchangeRate;
    }

    private function validateCampaignInput(mixed $input): void
    {
        if (null === $input) {
            throw new UnprocessableEntityHttpException('Field `campaign` is required');
        }
        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Field `campaign` must be an array');
        }
    }

    public function fetchCampaignsMedia(): JsonResponse
    {
        try {
            $taxonomy = $this->configurationRepository->fetchTaxonomy();
        } catch (MissingInitialConfigurationException $exception) {
            Log::error(sprintf('Error during fetching campaigns\' media: %s', $exception->getMessage()));
            return self::json(['campaignsMedia' => []]);
        }

        $mediaFromTaxonomy = [];
        foreach ($taxonomy->getMedia() as $mediumObject) {
            $mediaFromTaxonomy[$mediumObject->getName()][$mediumObject->getVendor()] =
                null === $mediumObject->getVendor()
                    ? $mediumObject->getLabel()
                    : sprintf('%s - %s', $mediumObject->getLabel(), $mediumObject->getVendorLabel());
        }

        $campaignsMedia = [];
        $previousMedium = null;
        $previousVendor = 'null';
        foreach ($this->campaignRepository->fetchCampaignsMedia() as $item) {
            if (!isset($mediaFromTaxonomy[$item->medium][$item->vendor])) {
                continue;
            }
            if (
                null !== $item->vendor
                && null !== $previousVendor
                && $item->medium !== $previousMedium
                && isset($mediaFromTaxonomy[$item->medium][$item->vendor])
            ) {
                $campaignsMedia[] = [
                    'medium' => $item->medium,
                    'vendor' => null,
                    'label' => $mediaFromTaxonomy[$item->medium][null]
                ];
            }
            $campaignsMedia[] = [
                'medium' => $item->medium,
                'vendor' => $item->vendor,
                'label' => $mediaFromTaxonomy[$item->medium][$item->vendor]
            ];
            $previousMedium = $item->medium;
            $previousVendor = $item->vendor;
        }

        return new JsonResponse(['campaignsMedia' => $campaignsMedia]);
    }
}

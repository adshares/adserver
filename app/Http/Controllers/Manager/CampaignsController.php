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
use Adshares\Adserver\Http\Requests\Campaign\CampaignTargetingProcessor;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Mail\Crm\CampaignCreated;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;
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
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CampaignsController extends Controller
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly ConfigurationRepository $configurationRepository,
        private readonly ExchangeRateReader $exchangeRateReader,
        private readonly BannerClassificationCreator $bannerClassificationCreator,
        private readonly BannerCreator $bannerCreator,
        private readonly ClassifierExternalRepository $classifierExternalRepository,
    ) {
    }

    public function upload(Request $request): UploadedFile
    {
        $mediumName = $request->get('medium');
        $vendor = $request->get('vendor');
        if (!is_string($mediumName)) {
            throw new UnprocessableEntityHttpException('Field `medium` must be a string');
        }
        if (null !== $vendor && !is_string($vendor)) {
            throw new UnprocessableEntityHttpException('Field `vendor` must be a string or null');
        }
        try {
            $medium = $this->configurationRepository->fetchMedium($mediumName, $vendor);
            return Factory::create($request)->upload($medium);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }
    }

    public function uploadPreview(Request $request, string $type, string $name): Response
    {
        try {
            return Factory::createFromType($type, $request)->preview($name);
        } catch (RuntimeException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }
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

        $this->createBannerClassificationsForCampaign($campaign);

        $this->sendCrmMailOnCampaignCreated($user, $campaign);

        return self::json($campaign->toArray(), Response::HTTP_CREATED)->header(
            'Location',
            route('app.campaigns.read', ['campaign_id' => $campaign->id])
        );
    }

    private function removeTemporaryUploadedFiles(array $files, Request $request): void
    {
        foreach ($files as $file) {
            if (!isset($file['uuid']) && isset($file['url'])) {
                $filename = Utils::extractFilename($file['url']);
                $uploader = Factory::createFromExtension($filename, $request);
                $uploader->removeTemporaryFile($filename);
            }
        }
    }

    private function prepareConversionsFromInput(array $input): array
    {
        $this->validateConversions($input);

        return $input;
    }

    public function browse(): JsonResponse
    {
        $campaigns = $this->campaignRepository->find();

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

        $status = $campaign->status;
        if ($input['basic_information']['medium'] !== $campaign->medium) {
            throw new UnprocessableEntityHttpException('Medium cannot be changed');
        }
        if ($input['basic_information']['vendor'] !== $campaign->vendor) {
            throw new UnprocessableEntityHttpException('Vendor cannot be changed');
        }
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

        $this->createBannerClassificationsForCampaign($campaign);

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
                        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
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

        $status = (int)$request->input('campaign.status');

        return $this->changeCampaignStatus($campaign, $status);
    }

    private function changeCampaignStatus(Campaign $campaign, int $status): JsonResponse
    {
        $exchangeRate = $this->fetchExchangeRateOrFail();

        if (!$campaign->changeStatus($status, $exchangeRate)) {
            throw new UnprocessableEntityHttpException(sprintf('Cannot set status to {%d}', $status));
        }

        $this->campaignRepository->update($campaign);

        $this->createBannerClassificationsForCampaign($campaign);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function activateOutdatedCampaign(Campaign $campaign): JsonResponse
    {
        $campaign->time_end = null;
        $campaign->status = Campaign::STATUS_SUSPENDED;

        return $this->changeCampaignStatus($campaign, Campaign::STATUS_ACTIVE);
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

        foreach ($campaign->banners as $banner) {
            $clonedBanner = $banner->replicate();
            $clonedBanner->campaign_id = $clonedCampaign->id;
            $clonedBanner->saveOrFail();
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

    private function createBannerClassificationsForCampaign(Campaign $campaign): void
    {
        if (
            Campaign::STATUS_ACTIVE !== $campaign->status
            || null === ($classifier = $this->classifierExternalRepository->fetchDefaultClassifier())
        ) {
            return;
        }

        $classifierName = $classifier->getName();

        $bannerIds = $campaign->banners()->where('status', Banner::STATUS_ACTIVE)->whereDoesntHave(
            'classifications',
            function ($query) use ($classifierName) {
                $query->where('classifier', $classifierName);
            }
        )->get()->pluck('id');

        if ($bannerIds->isEmpty()) {
            return;
        }

        $this->bannerClassificationCreator->create($classifierName, $bannerIds->toArray());
    }

    private function sendCrmMailOnCampaignCreated(User $user, Campaign $campaign): void
    {
        if (config('app.crm_mail_address_on_campaign_created')) {
            Mail::to(config('app.crm_mail_address_on_campaign_created'))->queue(
                new CampaignCreated($user->uuid, $user->email, $campaign)
            );
        }
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
}

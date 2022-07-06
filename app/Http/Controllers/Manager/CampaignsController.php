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
use Adshares\Adserver\Http\Requests\Campaign\MimeTypesValidator;
use Adshares\Adserver\Http\Requests\Campaign\TargetingProcessor;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Jobs\ClassifyCampaign;
use Adshares\Adserver\Mail\Crm\CampaignCreated;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\Notification;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;
use Adshares\Adserver\Uploader\Factory;
use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Video\VideoUploader;
use Adshares\Adserver\Uploader\Zip\ZipUploader;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Supply\Domain\ValueObject\Size;
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
    private CampaignRepository $campaignRepository;

    private ConfigurationRepository $configurationRepository;

    private ExchangeRateReader $exchangeRateReader;

    private BannerClassificationCreator $bannerClassificationCreator;

    private ClassifierExternalRepository $classifierExternalRepository;

    public function __construct(
        CampaignRepository $campaignRepository,
        ConfigurationRepository $configurationRepository,
        ExchangeRateReader $exchangeRateReader,
        BannerClassificationCreator $bannerClassificationCreator,
        ClassifierExternalRepository $classifierExternalRepository
    ) {
        $this->campaignRepository = $campaignRepository;
        $this->configurationRepository = $configurationRepository;
        $this->exchangeRateReader = $exchangeRateReader;
        $this->bannerClassificationCreator = $bannerClassificationCreator;
        $this->classifierExternalRepository = $classifierExternalRepository;
    }

    public function upload(Request $request): UploadedFile
    {
        try {
            return Factory::create($request)->upload();
        } catch (RuntimeException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
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
        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();
        } catch (ExchangeRateNotAvailableException $exception) {
            throw new ServiceUnavailableHttpException();
        }

        $this->validateRequestObject($request, 'campaign', Campaign::$rules);
        $input = $request->input('campaign');
        try {
            $input = $this->processTargeting($input);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }
        $bidStrategy = BidStrategy::fetchDefault(
            $input['basic_information']['medium'] ?? '',
            $input['basic_information']['vendor'] ?? null
        );
        if ($bidStrategy === null) {
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
        if (isset($input['ads']) && count($input['ads']) > 0) {
            $banners = $this->prepareBannersFromInput($input['ads'], $campaign->landing_url);
            $mimesValidator = new MimeTypesValidator($this->configurationRepository->fetchTaxonomy());
            try {
                $mimesValidator->validateMimeTypes($banners, $campaign->medium, $campaign->vendor);
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
            $this->campaignRepository->save($campaign);
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
                $filename = $this->filename($file['url']);
                $uploader = Factory::createFromExtension($filename, $request);
                $uploader->removeTemporaryFile($filename);
            }
        }
    }

    private function filename(string $imageUrl): string
    {
        return substr($imageUrl, strrpos($imageUrl, '/') + 1);
    }

    /**
     * @param array $input
     * @param string $campaignLandingUrl
     * @return Banner[]|array
     */
    private function prepareBannersFromInput(array $input, string $campaignLandingUrl): array
    {
        $banners = [];

        foreach ($input as $banner) {
            self::validateBanner($banner);
            $bannerModel = new Banner();
            $bannerModel->name = $banner['name'];
            $bannerModel->status = Banner::STATUS_ACTIVE;
            $size = $banner['creative_size'];
            $type = $banner['creative_type'];
            $bannerModel->creative_size = $size;
            $bannerModel->creative_type = $type;

            try {
                switch ($type) {
                    case Banner::TEXT_TYPE_IMAGE:
                        $fileName = $this->filename($banner['url']);
                        $content = ImageUploader::content($fileName);
                        $mimeType = ImageUploader::contentMimeType($fileName);
                        break;
                    case Banner::TEXT_TYPE_VIDEO:
                        $fileName = $this->filename($banner['url']);
                        $content = VideoUploader::content($fileName);
                        $mimeType = VideoUploader::contentMimeType($fileName);
                        break;
                    case Banner::TEXT_TYPE_MODEL:
                        $fileName = $this->filename($banner['url']);
                        $content = ModelUploader::content($fileName);
                        $mimeType = ModelUploader::contentMimeType($content);
                        break;
                    case Banner::TEXT_TYPE_HTML:
                        $content = ZipUploader::content($this->filename($banner['url']));
                        $mimeType = 'text/html';
                        break;
                    case Banner::TEXT_TYPE_DIRECT_LINK:
                    default:
                        $content = self::decorateUrlWithSize(
                            empty($banner['creative_contents']) ? $campaignLandingUrl : $banner['creative_contents'],
                            $size
                        );
                        $mimeType = 'text/plain';
                        break;
                }
            } catch (RuntimeException $exception) {
                Log::debug(
                    sprintf(
                        'Banner (name: %s, type: %s) could not be added (%s).',
                        $banner['name'],
                        $type,
                        $exception->getMessage()
                    )
                );

                continue;
            }

            $bannerModel->creative_contents = $content;
            $bannerModel->creative_mime = $mimeType;

            $banners[] = $bannerModel;
        }

        return $banners;
    }

    private static function decorateUrlWithSize(string $url, string $size): string
    {
        $sizeSuffix = '#' . $size;
        $length = strlen($sizeSuffix);

        return (substr($url, -$length) === $sizeSuffix) ? $url : $url . $sizeSuffix;
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
        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();
        } catch (ExchangeRateNotAvailableException $exception) {
            return self::json([], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->validateRequestObject(
            $request,
            'campaign',
            array_intersect_key(
                Campaign::$rules,
                $request->input('campaign')
            )
        );

        $input = $request->input('campaign');
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

            /** @var User $user */
            $user = Auth::user();
            $bidStrategy = BidStrategy::fetchByPublicId($bidStrategyUuid);

            if (
                null === $bidStrategy
                || ($bidStrategy->user_id !== $user->id && $bidStrategy->user_id !== BidStrategy::ADMINISTRATOR_ID)
            ) {
                throw new UnprocessableEntityHttpException('Bid strategy could not be accessed.');
            }

            $input['bid_strategy_uuid'] = $bidStrategyUuid;
        }
        $conversions = $this->prepareConversionsFromInput($input['conversions'] ?? []);

        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
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
                    $banner->creative_contents = self::decorateUrlWithSize(
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
            $bannersToInsert = $this->prepareBannersFromInput($banners->toArray(), $campaign->landing_url);
            $mimesValidator = new MimeTypesValidator($this->configurationRepository->fetchTaxonomy());
            try {
                $mimesValidator->validateMimeTypes($bannersToInsert, $campaign->medium, $campaign->vendor);
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

        $this->campaignRepository->update(
            $campaign,
            $bannersToInsert,
            $bannersToUpdate,
            $bannersToDelete,
            $conversionsToInsert,
            $conversionsToUpdate,
            $conversionUuidsToDelete
        );

        if ($ads) {
            $this->removeTemporaryUploadedFiles($ads, $request);
        }

        if ($campaign->changeStatus($status, $exchangeRate)) {
            $this->campaignRepository->save($campaign);
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
                        JsonResponse::HTTP_BAD_REQUEST
                    )
                );
            }
        }
    }

    public function changeStatus(Campaign $campaign, Request $request): JsonResponse
    {
        if (!$request->has('campaign.status')) {
            throw new InvalidArgumentException('No status provided');
        }

        $status = (int)$request->input('campaign.status');

        return $this->changeCampaignStatus($campaign, $status);
    }

    private function changeCampaignStatus(Campaign $campaign, int $status): JsonResponse
    {
        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();
        } catch (ExchangeRateNotAvailableException $exception) {
            return self::json([], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$campaign->changeStatus($status, $exchangeRate)) {
            return self::json([], Response::HTTP_BAD_REQUEST, ["Cannot set status to {$status}"]);
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

        if (!Banner::isStatusAllowed($status)) {
            $status = Banner::STATUS_INACTIVE;
        }

        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        /** @var Banner $banner */
        $banner = $campaign->banners()->where('id', $bannerId)->first();

        if (Banner::STATUS_REJECTED === $banner->status) {
            throw new BadRequestHttpException('Status cannot be changed. Banner was rejected.');
        }

        $banner->status = $status;

        $this->campaignRepository->update($campaign, [], [$banner], []);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function delete(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        if ($campaign->status !== Campaign::STATUS_INACTIVE) {
            $campaign->status = Campaign::STATUS_INACTIVE;
            $this->campaignRepository->save($campaign);
        }

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
        if (!$campaign) {
            throw new NotFoundHttpException(sprintf('Campaign %s does not exist.', $campaignId));
        }

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

    public function classify(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        $targetingRequires = ($campaign->targeting_requires) ? json_decode($campaign->targeting_requires, true) : null;
        $targetingExcludes = ($campaign->targeting_excludes) ? json_decode($campaign->targeting_excludes, true) : null;

        ClassifyCampaign::dispatch($campaignId, $targetingRequires, $targetingExcludes, []);

        $campaign->classification_status = 1;
        $campaign->update();

        Notification::add(
            $campaign->user_id,
            Notification::CLASSIFICATION_TYPE,
            'Classify queued',
            sprintf('Campaign %s has been queued to classify', $campaign->id)
        );

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function disableClassify(int $campaignId): void
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $campaign->classification_status = 0;
        $campaign->classification_tags = null;

        $campaign->update();
    }

    private function processTargeting(array $input): array
    {
        $medium = $input['basic_information']['medium'] ?? '';
        $vendor = $input['basic_information']['vendor'] ?? null;
        $targetingProcessor = new TargetingProcessor($this->configurationRepository->fetchTaxonomy());

        $requires = $input['targeting']['requires'] ?? [];
        $baseRequires = json_decode(config('app.campaign_targeting_require') ?? '', true);
        if (is_array($baseRequires)) {
            $requires = array_map([__CLASS__, 'normalize'], array_merge_recursive($requires, $baseRequires));
        }

        $excludes = $input['targeting']['excludes'] ?? [];
        $baseExcludes = json_decode(config('app.campaign_targeting_exclude') ?? '', true);
        if (is_array($baseExcludes)) {
            $excludes = array_map([__CLASS__, 'normalize'], array_merge_recursive($excludes, $baseExcludes));
        }

        $input['targeting_requires'] = $targetingProcessor->processTargeting($requires, $medium, $vendor);
        $input['targeting_excludes'] = $targetingProcessor->processTargeting($excludes, $medium, $vendor);

        return $input;
    }

    private static function normalize($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return $arr;
        }
        if (array_keys($arr) !== range(0, count($arr) - 1)) {
            return $arr;
        }
        return array_unique($arr);
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

    private static function validateBanner(array $banner): void
    {
        foreach (['creative_type', 'creative_size', 'name', 'url'] as $field) {
            if (!isset($banner[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `%s` is required', $field));
            }
            if (!is_string($banner[$field]) || strlen($banner[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a non-empty string', $field));
            }
        }

        $type = $banner['creative_type'] ?? null;
        if (!in_array($type, Banner::types())) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid type: %s.', $type));
        }
        $size = $banner['creative_size'];
        if ($type === Banner::TEXT_TYPE_VIDEO) {
            if (1 !== preg_match('/^[0-9]+x[0-9]+$/', $size)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid video size: %s.', $size));
            }
            if (empty(Size::findMatching(...Size::toDimensions($size)))) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid video size: %s. No match', $size));
            }
            return;
        }

        if (!Size::isValid($size)) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid banner size: %s.', $size));
        }
    }
}

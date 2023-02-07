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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\Common\LimitValidator;
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Filter\FilterType;
use Adshares\Adserver\Http\Resources\BannerResource;
use Adshares\Adserver\Http\Resources\CampaignResource;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Common\CrmNotifier;
use Adshares\Adserver\Services\Demand\BannerCreator;
use Adshares\Adserver\Services\Demand\CampaignCreator;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ApiCampaignsController extends Controller
{
    public function __construct(
        private readonly BannerCreator $bannerCreator,
        private readonly CampaignCreator $campaignCreator,
        private readonly CampaignRepository $campaignRepository,
    ) {
    }

    public function addCampaign(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $input = $request->input();
        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Invalid body type');
        }
        try {
            $campaign = $this->campaignCreator->prepareCampaignFromInput($input);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }
        $creatives = array_key_exists('creatives', $input) ? $input['creatives'] : [];
        if (!is_array($creatives)) {
            throw new UnprocessableEntityHttpException('Field `creatives` must be an array');
        }
        try {
            $banners = $this->bannerCreator->prepareBannersFromMetaData($creatives, $campaign);
            $campaign->user_id = $user->id;
            $campaign = $this->campaignRepository->save($campaign, $banners);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        CrmNotifier::sendCrmMailOnCampaignCreated($user, $campaign);
        self::removeTemporaryUploadedFiles($creatives);

        return (new CampaignResource($campaign))
            ->response()
            ->header(
                'Location',
                route('api.campaigns.fetch', [
                    'id' => Uuid::fromString($campaign->uuid)->toString(),
                ])
            );
    }

    public function editCampaignById(string $id, Request $request): JsonResource
    {
        $uuid = self::uuidFromString($id);
        $input = $request->input();
        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Invalid body type');
        }
        $campaign = $this->campaignRepository->fetchCampaignByUuid($uuid);
        try {
            $campaign = $this->campaignCreator->updateCampaign($input, $campaign);
            $campaign = $this->campaignRepository->update($campaign);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return new CampaignResource($campaign);
    }

    public function deleteCampaignById(string $id): JsonResponse
    {
        $uuid = self::uuidFromString($id);
        $campaign = $this->campaignRepository->fetchCampaignByUuid($uuid);
        $this->campaignRepository->delete($campaign);
        return new JsonResponse(['data' => []], Response::HTTP_OK);
    }

    public function fetchCampaignById(string $id): JsonResource
    {
        $uuid = self::uuidFromString($id);
        return new CampaignResource($this->campaignRepository->fetchCampaignByUuid($uuid));
    }

    public function fetchCampaigns(Request $request): JsonResource
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'medium' => FilterType::String,
            'vendor' => FilterType::String,
        ]);
        LimitValidator::validate($limit);
        $campaigns = $this->campaignRepository->fetchCampaigns($filters, $limit);
        return CampaignResource::collection($campaigns)->preserveQuery();
    }

    public function fetchBanner(string $campaignId, string $bannerId): JsonResource
    {
        $campaignUuid = self::uuidFromString($campaignId);
        $bannerUuid = self::uuidFromString($bannerId);
        $campaign = $this->campaignRepository->fetchCampaignByUuid($campaignUuid);
        $banner = $this->campaignRepository->fetchBannerByUuid($campaign, $bannerUuid);
        return new BannerResource($banner);
    }

    public function fetchBanners(string $campaignId, Request $request): JsonResource
    {
        $campaignUuid = self::uuidFromString($campaignId);
        $limit = $request->query('limit', 10);
        LimitValidator::validate($limit);
        $campaign = $this->campaignRepository->fetchCampaignByUuid($campaignUuid);
        $banners = $this->campaignRepository->fetchBanners($campaign, $limit);
        return BannerResource::collection($banners)->preserveQuery();
    }

    public function addBanner(string $campaignId, Request $request): JsonResponse
    {
        $campaignUuid = self::uuidFromString($campaignId);
        $campaign = $this->campaignRepository->fetchCampaignByUuid($campaignUuid);
        $oldBannerIds = $campaign->banners()->pluck('id');

        try {
            $banners = $this->bannerCreator->prepareBannersFromMetaData([$request->input()], $campaign);
            $this->campaignRepository->update($campaign, $banners);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        $bannerIds = $campaign->refresh()->banners()->pluck('id');
        $bannerId = $bannerIds->diff($oldBannerIds)->first();

        /** @var Banner $banner */
        $banner = $campaign->banners()->where('id', $bannerId)->first();

        self::removeTemporaryUploadedFiles([$request->input()]);

        return (new BannerResource($banner))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header(
                'Location',
                route('api.campaigns.creatives.fetch', [
                    'banner' => Uuid::fromString($banner->uuid)->toString(),
                    'campaign' => $campaignId,
                ])
            );
    }

    public function editBanner(string $campaignId, string $bannerId, Request $request): JsonResource
    {
        $campaignUuid = self::uuidFromString($campaignId);
        $bannerUuid = self::uuidFromString($bannerId);
        $input = $request->input();
        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Invalid body type');
        }

        $campaign = $this->campaignRepository->fetchCampaignByUuid($campaignUuid);
        $banner = $this->campaignRepository->fetchBannerByUuid($campaign, $bannerUuid);

        try {
            $banner = $this->bannerCreator->updateBanner($input, $banner);
            $this->campaignRepository->update($campaign, bannersToUpdate: [$banner]);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return new BannerResource($banner->refresh());
    }

    public function deleteBanner(string $campaignId, string $bannerId): JsonResponse
    {
        $campaignUuid = self::uuidFromString($campaignId);
        $bannerUuid = self::uuidFromString($bannerId);
        $campaign = $this->campaignRepository->fetchCampaignByUuid($campaignUuid);
        $banner = $this->campaignRepository->fetchBannerByUuid($campaign, $bannerUuid);

        try {
            $this->campaignRepository->update($campaign, bannersToDelete: [$banner]);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return new JsonResponse(['data' => []], Response::HTTP_OK);
    }

    public function upload(Request $request, CampaignsController $campaignsController): JsonResponse
    {
        $file = $campaignsController->upload($request);
        $data = $file->toArray();
        return new JsonResponse(['data' => ['id' => $data['name'], 'url' => $data['url']]]);
    }

    private static function removeTemporaryUploadedFiles(array $input): void
    {
        foreach ($input as $bannerMetaData) {
            Uploader::removeTemporaryFile(Uuid::fromString($bannerMetaData['file_id']));
        }
    }

    private static function uuidFromString(string $id): UuidInterface
    {
        try {
            return Uuid::fromString($id);
        } catch (InvalidUuidStringException) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid ID %s', $id));
        }
    }
}

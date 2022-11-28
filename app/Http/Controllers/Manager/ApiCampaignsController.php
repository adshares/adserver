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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\Common\LimitValidator;
use Adshares\Adserver\Http\Resources\CampaignCollection;
use Adshares\Adserver\Http\Resources\CampaignResource;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Common\CrmNotifier;
use Adshares\Adserver\Services\Demand\BannerCreator;
use Adshares\Adserver\Services\Demand\CampaignCreator;
use Adshares\Adserver\Uploader\Factory;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
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
        if (!isset($input['ads']) || !is_array($input['ads'])) {
            throw new UnprocessableEntityHttpException('Field `ads` must be an array');
        }
        try {
            $banners = $this->bannerCreator->prepareBannersFromInput($input['ads'], $campaign);
            $campaign->user_id = $user->id;
            $campaign = $this->campaignRepository->save($campaign, $banners);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        CrmNotifier::sendCrmMailOnCampaignCreated($user, $campaign);
        self::removeTemporaryUploadedFiles($input['ads'], $request);

        return (new CampaignResource($campaign))
            ->response()
            ->header(
                'Location',
                route('api.campaigns.fetch', [
                    'id' => $campaign->id,
                ])
            );
    }

    public function editCampaignById(int $id, Request $request): JsonResource
    {
        $input = $request->input();
        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Invalid body type');
        }
        $campaign = $this->campaignRepository->fetchCampaignByIdSimple($id);
        try {
            $campaign = $this->campaignCreator->updateCampaign($input, $campaign);
            $campaign = $this->campaignRepository->update($campaign);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return new CampaignResource($campaign);
    }

    public function deleteCampaignById(int $id): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignByIdSimple($id);
        $this->campaignRepository->delete($campaign);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function fetchCampaignById(int $id): JsonResource
    {
        return new CampaignResource($this->campaignRepository->fetchCampaignByIdSimple($id));
    }

    public function fetchCampaigns(Request $request): JsonResource
    {
        $limit = $request->query('limit', 10);
        LimitValidator::validate($limit);
        return new CampaignCollection($this->campaignRepository->fetchCampaigns($limit));
    }

    public function fetchBanner(int $campaignId, int $bannerId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignByIdSimple($campaignId);
        $banner = $this->campaignRepository->fetchBanner($campaign, $bannerId);

        return new JsonResponse(['data' => $banner]);
    }

    public function fetchBanners(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignByIdSimple($campaignId);
        $banners = $this->campaignRepository->fetchBanners($campaign);

        return new JsonResponse(['data' => $banners]);
    }

    public function addBanner(int $campaignId, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignByIdSimple($campaignId);
        $oldBannerIds = $campaign->banners()->pluck('id');

        try {
            $banners = $this->bannerCreator->prepareBannersFromInput([$request->input()], $campaign);
            $this->campaignRepository->update($campaign, $banners);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        $bannerIds = $campaign->refresh()->banners()->pluck('id');
        $bannerId = $bannerIds->diff($oldBannerIds)->first();

        $banner = $campaign->banners()->where('id', $bannerId)->first();

        self::removeTemporaryUploadedFiles([$request->input()], $request);

        return new JsonResponse(
            ['data' => $banner],
            Response::HTTP_CREATED,
            [
                'Location' => route('api.campaigns.banners.fetch', [
                    'banner' => $bannerId,
                    'campaign' => $campaignId,
                ]),
            ]
        );
    }

    public function editBanner(int $campaignId, int $bannerId, Request $request): JsonResponse
    {
        $input = $request->input();
        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Invalid body type');
        }

        $campaign = $this->campaignRepository->fetchCampaignByIdSimple($campaignId);
        $banner = $this->campaignRepository->fetchBanner($campaign, $bannerId);

        try {
            $banner = $this->bannerCreator->updateBanner($input, $banner);
            $this->campaignRepository->update($campaign, bannersToUpdate: [$banner]);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return new JsonResponse(['data' => $banner->refresh()]);
    }

    public function deleteBanner(int $campaignId, int $bannerId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignByIdSimple($campaignId);
        $banner = $this->campaignRepository->fetchBanner($campaign, $bannerId);

        try {
            $this->campaignRepository->update($campaign, bannersToDelete: [$banner]);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return new JsonResponse(['data' => []], Response::HTTP_OK);
    }

    private static function removeTemporaryUploadedFiles(array $input, Request $request): void
    {
        foreach ($input as $banner) {
            if (isset($banner['creative_type']) && isset($banner['url'])) {
                Factory::createFromType($banner['creative_type'], $request)
                    ->removeTemporaryFile(Utils::extractFilename($banner['url']));
            }
        }
    }
}

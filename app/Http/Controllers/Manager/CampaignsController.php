<?php declare(strict_types = 1);
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
use Adshares\Adserver\Jobs\ClassifyCampaign;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Notification;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Uploader\Factory;
use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Zip\ZipUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response as ResponseFacade;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function strrpos;

class CampaignsController extends Controller
{
    /**
     * @var CampaignRepository
     */
    private $campaignRepository;

    public function __construct(CampaignRepository $campaignRepository)
    {
        $this->campaignRepository = $campaignRepository;
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

        $response = ResponseFacade::make($banner->creative_contents, 200);

        if ($banner->creative_type === Banner::IMAGE_TYPE) {
            $response->header('Content-Type', 'image/png');
        }

        return $response;
    }

    public function add(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'campaign', Campaign::$rules);
        $input = $request->input('campaign');
        $status = $input['basic_information']['status'];

        $input['basic_information']['status'] = Campaign::STATUS_DRAFT;
        $input['user_id'] = Auth::user()->id;
        $input['targeting_requires'] = $request->input('campaign.targeting.requires');
        $input['targeting_excludes'] = $request->input('campaign.targeting.excludes');

        $banners = [];

        if (isset($input['ads']) && count($input['ads']) > 0) {
            $banners = $this->prepareBannersFromInput($input['ads']);
        }

        $campaign = new Campaign($input);
        $this->campaignRepository->save($campaign, $banners);

        $this->removeTemporaryUploadedFiles((array)$input['ads'], $request);

        try {
            $campaign->changeStatus($status);

            $this->campaignRepository->save($campaign);
        } catch (InvalidArgumentException $e) {
            Log::debug("Notify user [{$campaign->user_id}] that the campaign [{$campaign->id}] cannot be started.");
        }

        return self::json($campaign->toArray(), Response::HTTP_CREATED)->header(
            'Location',
            route('app.campaigns.read', ['campaign' => $campaign])
        );
    }

    private function removeTemporaryUploadedFiles(array $files, Request $request): void
    {
        foreach ($files as $file) {
            if (!isset($file['uuid'])) {
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

    private function prepareBannersFromInput(array $input): array
    {
        $banners = [];

        foreach ($input as $banner) {
            $size = explode('x', Banner::size($banner['size']));

            if (!isset($size[0], $size[1])) {
                throw new \RuntimeException('Banner size is required.');
            }

            $bannerModel = new Banner();
            $bannerModel->name = $banner['name'];
            $bannerModel->status = Banner::STATUS_ACTIVE;
            $bannerModel->creative_width = $size[0];
            $bannerModel->creative_height = $size[1];
            $bannerModel->creative_type = Banner::type($banner['type']);

            $fileName = $this->filename($banner['url']);

            try {
                if ($banner['type'] === Banner::HTML_TYPE) {
                    $content = ZipUploader::content($fileName);
                } else {
                    $content = ImageUploader::content($fileName);
                }
            } catch (RuntimeException $exception) {
                continue;
            }

            $bannerModel->creative_contents = $content;

            $banners[] = $bannerModel;
        }

        return $banners;
    }

    public function browse(): JsonResponse
    {
        $campaigns = $this->campaignRepository->find();

        return self::json($campaigns);
    }

    public function count(): JsonResponse
    {
        //@TODO: create function data
        $siteCount = [
            'totalBudget' => 0,
            'totalClicks' => 0,
            'totalImpressions' => 0,
            'averageCTR' => 0,
            'averageCPC' => 0,
            'totalCost' => 0,
        ];

        return self::json($siteCount);
    }

    public function edit(Request $request, int $campaignId): JsonResponse
    {
        $this->validateRequestObject(
            $request,
            'campaign',
            array_intersect_key(
                Campaign::$rules,
                $request->input('campaign')
            )
        );

        $input = $request->input('campaign');
        $input['targeting_requires'] = $request->input('campaign.targeting.requires');
        $input['targeting_excludes'] = $request->input('campaign.targeting.excludes');

        unset($input['status']); // Client cannot change status in EDIT action

        $ads = $request->input('campaign.ads');
        $banners = Collection::make($ads);

        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $status = $campaign->status;
        $campaign->fill($input);

        $campaign->changeStatus(Campaign::STATUS_INACTIVE);

        $bannersToUpdate = [];
        $bannersToDelete = [];
        $bannersToInsert = [];

        foreach ($campaign->banners as $banner) {
            $bannerFromInput = $banners->firstWhere('uuid', $banner->uuid);

            if ($bannerFromInput) {
                $banner->name = $bannerFromInput['name'];
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
            $bannersToInsert = $this->prepareBannersFromInput($banners->toArray());
        }

        $this->campaignRepository->update($campaign, $bannersToInsert, $bannersToUpdate, $bannersToDelete);

        if ($ads) {
            $this->removeTemporaryUploadedFiles($ads, $request);
        }


        if ($status !== $campaign->status) {
            try {
                $campaign->changeStatus($status);

                $this->campaignRepository->save($campaign);
            } catch (InvalidArgumentException $e) {
                Log::debug("Notify user [{$campaign->user_id}]"
                    ." that the campaign [{$campaign->id}] cannot be saved with status [{$status}]."
                    ." {$e->getMessage()}");
            }
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function changeStatus(Campaign $campaign, Request $request): JsonResponse
    {
        if (!$request->has('campaign.status')) {
            throw new InvalidArgumentException('No status provided');
        }

        $status = (int)$request->input('campaign.status');

        try {
            $campaign->changeStatus($status);
        } catch (InvalidArgumentException $e) {
            Log::debug("Notify user [{$campaign->user_id}]"
                ." that the campaign [{$campaign->id}] status cannot be set to [{$status}].");

            return self::json([], Response::HTTP_BAD_REQUEST, ["Cannot set status to {$status}"]);
        }

        $this->campaignRepository->update($campaign);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function changeBannerStatus(Request $request, int $campaignId, int $bannerId): JsonResponse
    {
        $status = (int)$request->input('banner.status');

        if (!Banner::isStatusAllowed($status)) {
            $status = Banner::STATUS_INACTIVE;
        }

        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        $banner = $campaign->banners()->where('id', $bannerId)->first();
        $banner->status = $status;

        $this->campaignRepository->update($campaign, [], [$banner], []);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function delete(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        if ($campaign->status !== Campaign::STATUS_INACTIVE) {
            $campaign->changeStatus(Campaign::STATUS_INACTIVE);
            $this->campaignRepository->save($campaign);
        }

        $this->campaignRepository->delete($campaign);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function read(int $campaignId): JsonResponse
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        return self::json(['campaign' => $campaign->toArray()]);
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
}

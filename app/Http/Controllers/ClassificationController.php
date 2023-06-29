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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Client\ClassifierExternalClient;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Mail\Notifications\CampaignAccepted;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use DateTime;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function sprintf;

class ClassificationController extends Controller
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly ClassifierExternalRepository $classifierRepository,
        private readonly ClassifierExternalSignatureVerifier $signatureVerifier,
    ) {
    }

    public function updateClassification(string $classifier, Request $request): JsonResponse
    {
        $this->validateClassifier($classifier);

        $isAnySignatureInvalid = false;

        $inputs = $request->all();
        $this->validateClassificationRequest($inputs, $classifier);

        $bannerPublicIds = [];
        foreach ($inputs as $input) {
            $bannerPublicIds[] = $input['id'];
        }
        $banners = Banner::fetchBannerByPublicIds($bannerPublicIds)->keyBy('uuid');
        $campaignIds = [];

        foreach ($inputs as $input) {
            /** @var $banner Banner */
            if (
                null === ($banner = $banners->get($input['id']))
                || null === ($classification =
                    BannerClassification::fetchByBannerIdAndClassifier($banner->id, $classifier))
            ) {
                Log::info(
                    sprintf(
                        '[classification update] Missing banner for id (%s) from classifier (%s)',
                        $input['id'],
                        $classifier
                    )
                );
                continue;
            }

            if (null !== ($errorCode = $input['error']['code'] ?? null)) {
                if (!isset($campaignIds[$banner->campaign_id])) {
                    $campaignIds[$banner->campaign_id] = true;
                }
                Log::info(
                    sprintf(
                        '[classification update] Error for banner id (%s) from classifier (%s): (code:%s)(message:%s)',
                        $input['id'],
                        $classifier,
                        $errorCode,
                        $input['error']['message'] ?? ''
                    )
                );

                $classification->failed();

                if (ClassifierExternalClient::CLASSIFIER_ERROR_CODE_BANNER_REJECTED === $errorCode) {
                    $banner->status = Banner::STATUS_REJECTED;
                    $banner->save();

                    Log::info(
                        sprintf(
                            '[classification update] Banner id (%s) was rejected by classifier (%s)',
                            $input['id'],
                            $classifier
                        )
                    );
                }
                continue;
            }

            $timestamp = $input['timestamp'];
            $signedAt = (new DateTime())->setTimestamp($timestamp);

            if (null !== $classification->signed_at && $signedAt <= $classification->signed_at) {
                Log::info(
                    sprintf(
                        '[classification update] Banner id (%s) has more recent classification from classifier (%s). '
                        . '%s <= %s',
                        $input['id'],
                        $classifier,
                        $signedAt->format(DateTimeInterface::ATOM),
                        $classification->signed_at->format(DateTimeInterface::ATOM)
                    )
                );
                continue;
            }

            if (!isset($campaignIds[$banner->campaign_id])) {
                $campaignIds[$banner->campaign_id] = true;
            }

            $signature = $input['signature'];
            $checksum = $banner->creative_sha1;
            $keywords = $input['keywords'] ?? [];

            if (
                !$this->signatureVerifier->isSignatureValid(
                    $classifier,
                    $signature,
                    $checksum,
                    $timestamp,
                    $keywords
                )
            ) {
                Log::info(
                    sprintf(
                        '[classification update] Invalid signature for banner id (%s),'
                        . ' checksum (%s) from classifier (%s)',
                        $input['id'],
                        $checksum,
                        $classifier
                    )
                );

                $isAnySignatureInvalid = true;
                $classification->failed();
                continue;
            }

            $classification->classified($keywords, $signature, $signedAt);
        }

        $this->sendMails($campaignIds, $classifier);

        if ($isAnySignatureInvalid) {
            return self::json(['message' => 'Invalid signature'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    private function validateClassifier(string $classifier): void
    {
        if (null === $this->classifierRepository->fetchClassifierByName($classifier)) {
            throw new NotFoundHttpException('Unknown classifier');
        }
    }

    private function validateClassificationRequest(array $inputs, string $classifier): void
    {
        if (empty($inputs)) {
            Log::info(
                sprintf(
                    '[classification update] No classification from classifier (%s)',
                    $classifier
                )
            );
            throw new UnprocessableEntityHttpException('No classification');
        }

        foreach ($inputs as $input) {
            if (!isset($input['id'])) {
                Log::info(sprintf('[classification update] Missing field `id` from classifier (%s)', $classifier));
                throw new UnprocessableEntityHttpException('Missing banner id');
            }
            if (isset($input['error'])) {
                if (!isset($input['error']['code'])) {
                    throw new UnprocessableEntityHttpException('Missing error code');
                }
                continue;
            }
            $requiredFields = ['signature', 'timestamp'];
            foreach ($requiredFields as $requiredField) {
                if (!isset($input[$requiredField])) {
                    Log::info(
                        sprintf(
                            '[classification update] Missing field `%s` from classifier (%s)',
                            $requiredField,
                            $classifier,
                        )
                    );
                    throw new UnprocessableEntityHttpException(sprintf('Missing %s', $requiredField));
                }
            }
        }
    }

    private function sendMails(array $campaignIds, string $classifier): void
    {
        if (empty($campaignIds)) {
            return;
        }

        $campaigns = $this->campaignRepository->fetchCampaignByIds(array_keys($campaignIds));
        foreach ($campaigns as $campaign) {
            $allClassified = $this->areAllCampaignBannersClassified($campaign, $classifier);
            if ($allClassified && null !== $campaign->user->email) {
                Mail::to($campaign->user->email)->queue(new CampaignAccepted($campaign));
            }
        }
    }

    private function areAllCampaignBannersClassified(Campaign $campaign, string $classifier): bool
    {
        $bannerIds = $campaign->banners->pluck('id')->toArray();
        $classificationStatuses = BannerClassification::fetchBannersClassificationStatus($bannerIds, $classifier);

        if (count($classificationStatuses) !== count($bannerIds)) {
            return false;
        }

        foreach ($classificationStatuses as $status) {
            if (
                BannerClassification::STATUS_NEW === $status ||
                BannerClassification::STATUS_IN_PROGRESS === $status
            ) {
                return false;
            }
        }
        return true;
    }
}

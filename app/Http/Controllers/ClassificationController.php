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

use Adshares\Adserver\Client\ClassifierExternalClient;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use function sprintf;

class ClassificationController extends Controller
{
    /** @var ClassifierExternalSignatureVerifier */
    private $signatureVerifier;

    public function __construct(ClassifierExternalSignatureVerifier $signatureVerifier)
    {
        $this->signatureVerifier = $signatureVerifier;
    }

    public function updateClassification(string $classifier, Request $request): JsonResponse
    {
        $isAnySignatureInvalid = false;

        $inputs = $request->all();
        $this->validateClassificationRequest($inputs, $classifier);

        $bannerPublicIds = [];
        foreach ($inputs as $input) {
            $bannerPublicIds[] = $input['id'];
        }
        $banners = Banner::fetchBannerByPublicIds($bannerPublicIds)->keyBy('uuid');

        foreach ($inputs as $input) {
            if (null === ($banner = $banners->get($input['id']))
                || null === ($classification =
                    BannerClassification::fetchByBannerIdAndClassifier($banner->id, $classifier))) {
                Log::info(
                    sprintf(
                        '[classification update] Missing banner id (%s) from classifier (%s)',
                        $input['id'],
                        $classifier
                    )
                );

                continue;
            }

            if (null !== ($errorCode = $input['error']['code'] ?? null)) {
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
                        .'%s <= %s',
                        $input['id'],
                        $classifier,
                        $signedAt->format(DateTime::ATOM),
                        $classification->signed_at->format(DateTime::ATOM)
                    )
                );

                continue;
            }

            $signature = $input['signature'];
            $checksum = $banner->creative_sha1;
            $keywords = $input['keywords'] ?? [];

            if (!$this->signatureVerifier->isSignatureValid(
                $classifier,
                $signature,
                $checksum,
                $timestamp,
                $keywords
            )) {
                Log::info(
                    sprintf(
                        '[classification update] Invalid signature for banner id (%s),'
                        .' checksum (%s) from classifier (%s)',
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

        if ($isAnySignatureInvalid) {
            return self::json(['message' => 'Invalid signature'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return self::json([], Response::HTTP_NO_CONTENT);
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
                Log::info(
                    sprintf(
                        '[classification update] Missing field banner id from classifier (%s)',
                        $classifier
                    )
                );

                throw new UnprocessableEntityHttpException('Missing banner id');
            }

            if (isset($input['error'])) {
                if (!isset($input['error']['code'])) {
                    throw new UnprocessableEntityHttpException('Missing error code');
                }

                continue;
            }

            if (!isset($input['signature'])) {
                Log::info(
                    sprintf(
                        '[classification update] Missing field signature from classifier (%s)',
                        $classifier
                    )
                );

                throw new UnprocessableEntityHttpException('Missing signature');
            }

            if (!isset($input['timestamp'])) {
                Log::info(
                    sprintf(
                        '[classification update] Missing field timestamp from classifier (%s)',
                        $classifier
                    )
                );

                throw new UnprocessableEntityHttpException('Missing timestamp');
            }
        }
    }
}

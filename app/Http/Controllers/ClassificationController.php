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

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $inputs = $request->all();
        foreach ($inputs as $input) {
            if (null === ($banner = Banner::fetchBanner($input['id']))
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

            $signature = $input['signature'];
            $checksum = $banner->creative_sha1;
            $keywords = $input['keywords'];

            if (!$this->signatureVerifier->isSignatureValid($classifier, $signature, $checksum, $keywords)) {
                Log::info(
                    sprintf(
                        '[classification update] Invalid signature for banner checksum (%s) from classifier (%s)',
                        $checksum,
                        $classifier
                    )
                );

                $classification->failed();

                continue;
            }

            $classification->classified($keywords, $signature);
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }
}

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
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use Illuminate\Http\Request;
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
            $signature = $input['signature'];
            $checksum = $input['checksum'];
            $keywords = $input['keywords'];

            if (!$this->signatureVerifier->isSignatureValid($classifier, $signature, $checksum, $keywords)) {
                Log::info(
                    sprintf(
                        '[classification update] Invalid signature for banner checksum (%s) from classifier (%s)',
                        $checksum,
                        $classifier
                    )
                );

                continue;
            }

            if (null === ($classification =
                    BannerClassification::fetchByChecksumAndClassifier($checksum, $classifier))) {
                Log::info(
                    sprintf(
                        '[classification update] Missing banner checksum (%s) from classifier (%s)',
                        $checksum,
                        $classifier
                    )
                );

                continue;
            }

            $classification->keywords = $keywords;
            $classification->signature = $signature;
            $classification->status = BannerClassification::STATUS_SUCCESS;
            $classification->save();
        }

        return self::json();
    }
}

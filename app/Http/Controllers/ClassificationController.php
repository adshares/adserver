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
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SodiumException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use function sprintf;

class ClassificationController extends Controller
{
    /** @var ClassifierExternalRepository */
    private $classifierRepository;

    public function __construct(ClassifierExternalRepository $classifierRepository)
    {
        $this->classifierRepository = $classifierRepository;
    }

    public function updateClassification(string $classifier, Request $request): JsonResponse
    {
        if (null === ($publicKey = $this->classifierRepository->fetchPublicKeyByClassifierName($classifier))) {
            return self::json([], Response::HTTP_NOT_FOUND);
        }

        $inputs = $request->all();
        foreach ($inputs as $input) {
            $checksum = $input['checksum'];
            $keywords = $input['keywords'];
            $signature = $input['signature'];

            $message = $this->createMessage($checksum, $keywords);

            if (!$this->isSignatureValid($publicKey, $message, $signature)) {
                Log::warning(
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
                Log::warning(
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

    private function createMessage(string $checksum, array $keywords): string
    {
        ksort($keywords);

        return sha1($checksum.json_encode($keywords));
    }

    private function isSignatureValid(string $publicKey, string $message, string $signature): bool
    {
        try {
            return sodium_crypto_sign_verify_detached(hex2bin($signature), $message, hex2bin($publicKey));
        } catch (SodiumException $exception) {
            return false;
        }
    }
}

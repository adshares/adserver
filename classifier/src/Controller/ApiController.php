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

declare(strict_types = 1);

namespace App\Controller;

use App\Verifier\BannerVerifierInterface;
use App\Verifier\Exception\BannerNotVerifiedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiController
{
    /** @var BannerVerifierInterface */
    private $bannerVerifier;

    public function __construct(BannerVerifierInterface $bannerVerifier)
    {
        $this->bannerVerifier = $bannerVerifier;
    }

    public function verifyAction(string $id): JsonResponse
    {
        try {
            $verification = $this->bannerVerifier->fetchVerifiedBanner($id);
        } catch (BannerNotVerifiedException $exception) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($verification->toArray(), Response::HTTP_OK);
    }
}

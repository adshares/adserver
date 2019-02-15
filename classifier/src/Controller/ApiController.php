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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController
{
    /** @var BannerVerifierInterface */
    private $bannerVerifier;

    public function __construct(BannerVerifierInterface $bannerVerifier)
    {
        $this->bannerVerifier = $bannerVerifier;
    }

    public function fetchAction(Request $request): JsonResponse
    {
        $bannerIds = json_decode($request->getContent(), true);

        $results = [];
        foreach ($bannerIds as $bannerId) {
            try {
                $verification = $this->bannerVerifier->fetchVerifiedBanner($bannerId);
                $results[$bannerId] = $verification->toArray();
            } catch (BannerNotVerifiedException $exception) {
                $results[$bannerId] = null;
            }
        }

        return new JsonResponse($results, Response::HTTP_OK);
    }
}

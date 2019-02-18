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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\Controller;
use Adshares\Classify\Application\Exception\BannerNotVerifiedException;
use Adshares\Classify\Application\Service\BannerClassifierInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClassifyController extends Controller
{
    private $bannerVerifier;

    public function __construct(BannerClassifierInterface $bannerVerifier)
    {
        $this->bannerVerifier = $bannerVerifier;
    }

    public function fetch(Request $request): JsonResponse
    {
        $bannerIds = json_decode($request->getContent(), true);

        $results = [];
        foreach ($bannerIds as $bannerId) {
            try {
                $verification = $this->bannerVerifier->fetchClassifiedBanner($bannerId);
                $results[$bannerId] = $verification->toArray();
            } catch (BannerNotVerifiedException $exception) {
                $results[$bannerId] = null;
            }
        }

        return new JsonResponse($results, Response::HTTP_OK);
    }
}

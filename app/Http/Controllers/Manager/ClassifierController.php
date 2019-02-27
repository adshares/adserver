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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Response\Classifier\ClassifierResponse;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Classify\Application\Service\SignatureVerifierInterface;
use Adshares\Classify\Domain\Model\Classification as DomainClassification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClassifierController extends Controller
{
    /** @var SignatureVerifierInterface */
    private $signatureVerifier;

    public function __construct(SignatureVerifierInterface $signatureVerifier)
    {
        $this->signatureVerifier = $signatureVerifier;
    }

    public function fetch(Request $request, ?int $siteId = null): JsonResponse
    {
        $limit = (int)$request->get('limit', 20);
        $offset = (int)$request->get('offset', 0);
        $banners = NetworkBanner::fetch($limit, $offset);
        $bannerIds = $this->getIdsFromBanners($banners);
        $classifications = Classification::fetchByBannerIds($bannerIds);

        $response = new ClassifierResponse($banners, $classifications, $siteId);

        return self::json($response);
    }

    private function getIdsFromBanners(Collection $banners): array
    {
        return $banners->map(
            function (NetworkBanner $banner) {
                return $banner->id;
            }
        )->toArray();
    }

    public function add(Request $request, int $siteId = null): JsonResponse
    {
        $input = $request->request->all();
        $classification = $input['classification'];
        $userId = (int)Auth::user()->id;

        if (!isset($input['classification'], $classification['banner_id'], $classification['status'])) {
            throw new BadRequestHttpException('Wrong input parameters.');
        }

        $bannerId = (int)$classification['banner_id'];
        $status = (bool)$classification['status'];
        $banner = NetworkBanner::find($bannerId);

        if (!$banner) {
            throw new NotFoundHttpException(sprintf('Banner %s does not exist.', $bannerId));
        }

        $classificationDomain = new DomainClassification(
            (string)config('app.classify_namespace'),
            $userId,
            $status,
            '',
            $siteId
        );

        $signature = $this->signatureVerifier->create($classificationDomain->keyword(), $banner->uuid);
        $classificationDomain->sign($signature);

        try {
            Classification::classify($userId, $bannerId, $status, $signature, $siteId);
        } catch (QueryException $exception) {
            throw new AccessDeniedHttpException('Operation cannot be proceed. Wrong permissions.');
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }
}

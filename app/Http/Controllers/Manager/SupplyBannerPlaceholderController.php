<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Filter\FilterType;
use Adshares\Adserver\Http\Resources\SupplyBannerPlaceholderResource;
use Adshares\Adserver\Models\SupplyBannerPlaceholder;
use Adshares\Adserver\Services\Supply\BannerPlaceholderProvider;
use Adshares\Adserver\Uploader\PlaceholderUploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Format;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Dto\TaxonomyV2\Targeting;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Domain\Adapter\ArrayableItemCollection;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\Model\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SupplyBannerPlaceholderController extends Controller
{
    public function __construct(
        private readonly ConfigurationRepository $configurationRepository,
        private readonly BannerPlaceholderProvider $bannerPlaceholderProvider,
        private readonly PlaceholderUploader $placeholderUploader,
    ) {
    }

    public function fetchPlaceholders(Request $request): JsonResource
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'medium' => FilterType::String,
            'type' => FilterType::String,
            'mime' => FilterType::String,
        ]);

        return SupplyBannerPlaceholderResource::collection(
            $this->bannerPlaceholderProvider->fetchByFilters($filters, $limit)
        )->preserveQuery();
    }

    public function deletePlaceholder(string $uuid): JsonResponse
    {
        if (!Uuid::isValid($uuid)) {
            throw new UnprocessableEntityHttpException('Invalid ID');
        }
        $uuid = str_replace('-', '', $uuid);
        /** @var SupplyBannerPlaceholder $bannerPlaceholder */
        if (null === ($bannerPlaceholder = SupplyBannerPlaceholder::fetchByPublicId($uuid))) {
            throw new NotFoundHttpException();
        }
        try {
            $this->bannerPlaceholderProvider->deleteBannerPlaceholder($bannerPlaceholder);
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        return self::json(code: Response::HTTP_NO_CONTENT);
    }

    public function uploadPlaceholder(Request $request): JsonResponse
    {
        if (Banner::TYPE_IMAGE !== $request->get('type')) {
            throw new UnprocessableEntityHttpException('Field `type` must be `image`');
        }
        $mediumName = $request->get('medium');
        if (!is_string($mediumName)) {
            throw new UnprocessableEntityHttpException('Field `medium` must be a string');
        }

        $files = $request->allFiles();
        if (0 === count($files)) {
            throw new UnprocessableEntityHttpException('At least one file is required');
        }

        try {
            $medium = $this->mergeMediaByName($mediumName);
            foreach ($files as $file) {
                $uuid = $this->placeholderUploader->upload($file, $medium);
            }
        } catch (InvalidArgumentException | RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        $lastPlaceholder = SupplyBannerPlaceholder::fetchByPublicId($uuid);
        $data = (new SupplyBannerPlaceholderResource($lastPlaceholder))->toArray($request);

        return self::json($data, Response::HTTP_CREATED)
            ->header('Location', $lastPlaceholder->serve_url);
    }

    private function mergeMediaByName(string $mediumName): Medium
    {
        $formatsData = [];
        foreach ($this->configurationRepository->fetchTaxonomy()->getMedia() as $medium) {
            if ($medium->getName() === $mediumName) {
                foreach ($medium->getFormats() as $format) {
                    if (isset($formatsData[$format->getType()])) {
                        $formatsData[$format->getType()]['mimes'] = array_unique(
                            array_merge(
                                $formatsData[$format->getType()]['mimes'],
                                $format->getMimes(),
                            )
                        );
                        $formatsData[$format->getType()]['scopes'] = array_unique(
                            array_merge(
                                $formatsData[$format->getType()]['scopes'],
                                $format->getScopes(),
                            )
                        );
                    } else {
                        $formatsData[$format->getType()] = [
                            'type' => $format->getType(),
                            'mimes' => $format->getMimes(),
                            'scopes' => $format->getScopes(),
                        ];
                    }
                }
            }
        }
        $formats = new ArrayableItemCollection();
        foreach ($formatsData as $formatData) {
            $formats->add(Format::fromArray($formatData));
        }
        return new Medium(
            $mediumName,
            $mediumName,
            null,
            null,
            $formats,
            new Targeting(
                new ArrayableItemCollection(),
                new ArrayableItemCollection(),
                new ArrayableItemCollection(),
            )
        );
    }
}

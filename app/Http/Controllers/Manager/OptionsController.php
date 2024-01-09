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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\TargetingReachRequest;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Advertiser\TargetingReachComputer;
use Adshares\Adserver\ViewModel\OptionsSelector;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OptionsController extends Controller
{
    public function __construct(
        private readonly ConfigurationRepository $optionsRepository,
        private readonly ClassifierExternalRepository $classifierRepository,
    ) {
    }

    public function banners(): JsonResponse
    {
        return self::json(
            [
                'uploadLimitImage' => config('app.upload_limit_image'),
                'uploadLimitModel' => config('app.upload_limit_model'),
                'uploadLimitVideo' => config('app.upload_limit_video'),
                'uploadLimitZip' => config('app.upload_limit_zip'),
            ]
        );
    }

    public function campaigns(): JsonResponse
    {
        return self::json(
            [
                'minBudget' => config('app.campaign_min_budget'),
                'minCpm' => config('app.campaign_min_cpm'),
                'minCpa' => config('app.campaign_min_cpa'),
            ]
        );
    }

    public function sites(): JsonResponse
    {
        return self::json(
            [
                'acceptBannersManually' => config('app.site_accept_banners_manually'),
                'classifierLocalBanners' => config('app.site_classifier_local_banners'),
                'directLinkEnabled' => config('app.supply_direct_link_enabled'),
            ]
        );
    }

    public function media(): JsonResponse
    {
        try {
            $media = $this->optionsRepository->fetchMedia();
        } catch (MissingInitialConfigurationException) {
            return self::json();
        }
        return self::json($media->toArray());
    }

    public function medium(string $medium, Request $request): JsonResponse
    {
        $vendor = $request->get('vendor');
        try {
            $mediumObject = $this->optionsRepository->fetchMedium($medium, $vendor);
        } catch (MissingInitialConfigurationException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }
        $data = $mediumObject->toArray();

        if ($request->get('e')) {
            foreach ($data['targeting']['site'] ?? [] as $key => $value) {
                if ($value['name'] === 'quality') {
                    unset($data['targeting']['site'][$key]);
                    $data['targeting']['site'] = array_values($data['targeting']['site']);
                    break;
                }
            }
        }

        return self::json($data);
    }

    public function vendors(string $medium): JsonResponse
    {
        $data = new stdClass();
        try {
            $taxonomy = $this->optionsRepository->fetchTaxonomy();
        } catch (MissingInitialConfigurationException $exception) {
            Log::error(sprintf('No taxonomy (%s)', $exception->getMessage()));
            return self::json($data);
        }
        foreach ($taxonomy->getMedia() as $mediumObject) {
            if ($mediumObject->getName() === $medium && $mediumObject->getVendor() !== null) {
                $data->{$mediumObject->getVendor()} = $mediumObject->getVendorLabel();
            }
        }
        return self::json($data);
    }

    public function targetingReach(TargetingReachRequest $request): JsonResponse
    {
        $targeting = $request->toArray()['targeting'];

        $requires = AbstractFilterMapper::generateNestedStructure($targeting['requires']);
        $excludes = AbstractFilterMapper::generateNestedStructure($targeting['excludes']);

        $targetingReach = (new TargetingReachComputer())->compute($requires, $excludes);

        return self::json($targetingReach->toArray());
    }

    public function filtering(Request $request): JsonResponse
    {
        $exclusions = [];
        if ($request->get('e')) {
            $exclusions = [
                sprintf(
                    '/%s:quality',
                    $this->classifierRepository->fetchDefaultClassifierName()
                ) => true
            ];
        }
        try {
            $selector = $this->optionsRepository->fetchFilteringOptions();
        } catch (MissingInitialConfigurationException) {
            return self::json();
        }
        return self::json(new OptionsSelector($selector->exclude($exclusions)));
    }

    public function languages(): JsonResponse
    {
        return self::json(Simulator::getAvailableLanguages());
    }

    public function server(): JsonResponse
    {
        return self::json(
            [
                'app_currency' => Currency::from(config('app.currency'))->value,
                'display_currency' => Currency::from(config('app.display_currency'))->value,
                'support_chat' => config('app.support_chat'),
                'support_email' => config('app.support_email'),
                'support_telegram' => config('app.support_telegram'),
            ]
        );
    }

    public function defaultUserRoles(): JsonResponse
    {
        return self::json(
            [
                'default_user_roles' => config('app.default_user_roles'),
            ]
        );
    }

    public function zones(): JsonResponse
    {
        try {
            $medium = $this->optionsRepository->fetchMedium();
        } catch (MissingInitialConfigurationException) {
            return self::json();
        }

        $types = [];
        foreach ($medium->getFormats() as $format) {
            foreach ($format->getScopes() as $size => $label) {
                if (isset($types[$size])) {
                    continue;
                }
                $types[$size] = [
                    'label' => $label,
                    'size' => $size,
                    'type' => Utils::getZoneTypeByBannerType($format->getType()),
                ];
            }
        }
        return self::json(array_values($types));
    }
}

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
use Adshares\Adserver\Http\Requests\GetSiteCode;
use Adshares\Adserver\Http\Response\Site\SizesResponse;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SiteRejectReason;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Services\Common\CrmNotifier;
use Adshares\Adserver\Services\Common\MetaverseAddressValidator;
use Adshares\Adserver\Services\Publisher\SiteCategoriesValidator;
use Adshares\Adserver\Services\Publisher\SiteCodeGenerator;
use Adshares\Adserver\Services\Supply\SiteFilteringUpdater;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\Utilities\SiteValidator;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Common\Application\Dto\PageRank;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\InvalidArgumentException;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SitesController extends Controller
{
    public function __construct(
        private readonly ConfigurationRepository $configurationRepository,
        private readonly SiteCategoriesValidator $siteCategoriesValidator,
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'site', Site::$rules);

        $input = $request->input('site');

        if (!in_array($input['status'], Site::ALLOWED_STATUSES, true)) {
            throw new UnprocessableEntityHttpException('Invalid status');
        }

        if (!SiteValidator::isUrlValid($input['url'] ?? null)) {
            throw new UnprocessableEntityHttpException('Invalid URL');
        }
        $url = (string)$input['url'];
        $domain = DomainReader::domain($url);
        $medium = $input['medium'] ?? null;
        $vendor = $input['vendor'] ?? null;
        self::validateMedium($medium);
        self::validateVendor($vendor);
        self::validateDomain($domain, $medium, $vendor);

        try {
            $categoriesByUser = $this->siteCategoriesValidator->processCategories(
                $input['categories'] ?? null,
                $medium,
                $vendor
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        $siteClassifierSetting = config('app.site_classifier_local_banners');
        if (Config::CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY === $siteClassifierSetting) {
            $onlyAcceptedBanners = true;
        } else {
            $defaultSetting = Config::CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT !== $siteClassifierSetting;
            $onlyAcceptedBanners = $input['only_accepted_banners'] ?? $defaultSetting;
            if (!is_bool($onlyAcceptedBanners)) {
                throw new UnprocessableEntityHttpException('Field `only_accepted_banners` must be a boolean');
            }
        }

        $inputZones = $input['ad_units'] ?? null;
        $this->validateInputZones($this->configurationRepository->fetchMedium($medium, $vendor), $inputZones);
        $filtering = $input['filtering'] ?? null;
        $this->validateFiltering($filtering);

        /** @var User $user */
        $user = Auth::user();
        if (null !== Site::fetchSite($user->id, $domain)) {
            throw new UnprocessableEntityHttpException(sprintf('Site with domain `%s` exists', $domain));
        }

        DB::beginTransaction();

        try {
            $site = Site::create(
                $user->id,
                $url,
                $input['name'],
                $medium,
                $vendor,
                $onlyAcceptedBanners,
                $input['status'],
                $input['primary_language'],
                $categoriesByUser,
                $filtering,
            );

            if ($inputZones) {
                $site->zones()->createMany($this->processInputZones($site, $inputZones));
            }
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        CrmNotifier::sendCrmMailOnSiteAdded($user, $site);

        return self::json(['data' => $site->refresh()->toArray()], Response::HTTP_CREATED)
            ->header('Location', route('app.sites.read', ['site' => $site->id]));
    }

    public function read(Site $site): JsonResponse
    {
        return self::json($this->prepareSiteObject($site));
    }

    private function prepareSiteObject(Site $site): array
    {
        $siteArray = $site->toArray();
        $siteArray['filtering'] = $this->processClassificationInFiltering($siteArray['filtering']);
        $siteArray['ad_units'] = $this->appendAdUnitsLabels($siteArray['ad_units'], $this->getLabelBySize($site));

        return $siteArray;
    }

    private function processClassificationInFiltering(array $filtering): array
    {
        $filtering['requires'] = array_filter(
            $filtering['requires'] ?: [],
            $this->filterOutHelperKeywords(),
            ARRAY_FILTER_USE_KEY
        );
        $filtering['excludes'] = array_filter(
            $filtering['excludes'] ?: [],
            $this->filterOutHelperKeywords(),
            ARRAY_FILTER_USE_KEY
        );

        return $filtering;
    }

    private function filterOutHelperKeywords(): Closure
    {
        return function ($key) {
            return SiteFilteringUpdater::INTERNAL_CLASSIFIER_NAMESPACE !== $key
                && false === strpos($key, SiteFilteringUpdater::KEYWORD_CLASSIFIED);
        };
    }

    private function appendAdUnitsLabels(array $adUnits, array $labelBySize): array
    {
        foreach ($adUnits as &$adUnit) {
            $adUnit['label'] = $labelBySize[$adUnit['size']] ?? '';
        }
        return $adUnits;
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        if (Site::STATUS_PENDING_APPROVAL === $site->status) {
            throw new UnprocessableEntityHttpException('Site is pending approval');
        }
        $input = $request->input('site');
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $input));
        $updateDomainAndUrl = false;
        if (isset($input['status']) && !in_array($input['status'], Site::ALLOWED_STATUSES, true)) {
            throw new UnprocessableEntityHttpException('Invalid status');
        }
        if (isset($input['url'])) {
            if (!SiteValidator::isUrlValid($input['url'])) {
                throw new UnprocessableEntityHttpException('Invalid URL');
            }
            $url = (string)$input['url'];
            $domain = DomainReader::domain($url);
            self::validateDomain($domain, $site->medium, $site->vendor);

            $input['domain'] = $domain;
            $updateDomainAndUrl = $site->domain !== $domain || $site->url !== $url;
            if ($updateDomainAndUrl && null !== Site::fetchSite($site->user_id, $domain)) {
                throw new UnprocessableEntityHttpException(sprintf('Site with domain `%s` exists', $domain));
            }
        }
        if (isset($input['only_accepted_banners'])) {
            if (!is_bool($input['only_accepted_banners'])) {
                throw new UnprocessableEntityHttpException('Field `only_accepted_banners` must be a boolean');
            }
            $siteClassifierSetting = config('app.site_classifier_local_banners');
            if (
                Config::CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY === $siteClassifierSetting
                && !$input['only_accepted_banners']
            ) {
                throw new UnprocessableEntityHttpException('Field `only_accepted_banners` cannot be changed');
            }
        }
        $inputZones = $request->input('site.ad_units');
        $this->validateInputZones(
            $this->configurationRepository->fetchMedium($site->medium, $site->vendor),
            $inputZones
        );

        DB::beginTransaction();

        try {
            $site->fill($input);
            if ($updateDomainAndUrl) {
                $site->accepted_at = null;
                $site->ads_txt_confirmed_at = null;
            }
            if (Site::STATUS_ACTIVE === $site->status) {
                $site->approvalProcedure();
            }
            $site->push();
            resolve(SiteFilteringUpdater::class)->addClassificationToFiltering($site);

            if ($inputZones) {
                $site->zones()->createMany($this->processInputZones($site, $inputZones));
            }

            if ($updateDomainAndUrl) {
                $site->updateWithPageRank(PageRank::default());
            }
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json(['data' => $site->refresh()->toArray()]);
    }

    private function processInputZones(Site $site, array $inputZones): array
    {
        $presentUniqueSizes = [];
        $keysToRemove = [];
        $bannerTypeBySize = $this->getBannerTypeBySize($site);

        foreach ($inputZones as $key => &$inputZone) {
            $size = $inputZone['size'];
            $inputZone['scopes'] = [$size];
            $type = Utils::getZoneTypeByBannerType($bannerTypeBySize[$size]);
            $inputZone['type'] = $type;

            if (Zone::TYPE_POP !== $type) {
                continue;
            }

            if (isset($presentUniqueSizes[$size])) {
                $keysToRemove[] = $key;
            } else {
                $presentUniqueSizes[$size] = $key;
            }
        }
        unset($inputZone);

        foreach ($keysToRemove as $key) {
            unset($inputZones[$key]);
        }

        /** @var Zone $zone */
        foreach ($site->zones()->withTrashed()->get() as $zone) {
            if (Zone::TYPE_POP === $zone->type) {
                $size = $zone->size;

                if (isset($presentUniqueSizes[$size])) {
                    if ($zone->trashed()) {
                        $zone->restore();
                    }

                    $key = $presentUniqueSizes[$size];
                    $zone->update($inputZones[$key]);
                    unset($inputZones[$key]);
                } else {
                    if (!$zone->trashed()) {
                        $zone->delete();
                    }
                }

                continue;
            }

            if ($zone->trashed()) {
                continue;
            }

            $zoneFromInput = null;
            foreach ($inputZones as $key => $inputZone) {
                if (($inputZone['id'] ?? null) === $zone->id) {
                    $zoneFromInput = $inputZone;
                    unset($inputZones[$key]);

                    break;
                }
            }

            if (null !== $zoneFromInput) {
                $zone->update($zoneFromInput);
            } else {
                $zone->delete();
            }
        }

        return $inputZones;
    }

    public function delete(Site $site): JsonResponse
    {
        DB::beginTransaction();

        try {
            $site->delete();
            $site->zones()->delete();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json(['message' => 'Successfully deleted']);
    }

    public function list(): JsonResponse
    {
        $siteCollection = Site::get();
        $sites = [];
        foreach ($siteCollection as $site) {
            $sites[] = $this->prepareSiteObject($site);
        }

        return self::json($sites);
    }

    public function readSitesSizes(?int $siteId = null): JsonResponse
    {
        $response = new SizesResponse($siteId);

        return self::json($response);
    }

    public function readSiteRank(Site $site): JsonResponse
    {
        return self::json(new PageRank($site->rank, $site->info));
    }

    public function sitesCodes(Site $site, GetSiteCode $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->is_confirmed) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Confirm account to get code');
        }
        if (in_array($site->status, [Site::STATUS_PENDING_APPROVAL, Site::STATUS_REJECTED], true)) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Site must be verified');
        }
        return self::json(['codes' => SiteCodeGenerator::generate($site, $request->toConfig())]);
    }

    public function sitesCryptovoxelsCode(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_confirmed) {
            throw new AccessDeniedHttpException('Confirm account to get code');
        }
        if ($user->wallet_address === null) {
            throw new UnprocessableEntityHttpException('Connect wallet to get code');
        }

        return self::json(
            [
                'code' => SiteCodeGenerator::generateCryptovoxels(
                    new SecureUrl(config('app.url')),
                    $user->wallet_address
                )
            ]
        );
    }

    private function validateInputZones(Medium $medium, $inputZones): void
    {
        if (null === $inputZones) {
            return;
        }

        if (!is_array($inputZones)) {
            throw new UnprocessableEntityHttpException('Invalid ad units type.');
        }

        $allowedSizes = $this->getAllowedSizes($medium);
        foreach ($inputZones as $inputZone) {
            if (!isset($inputZone['name']) || !is_string($inputZone['name'])) {
                throw new UnprocessableEntityHttpException('Invalid name.');
            }
            if (
                !isset($inputZone['size'])
                || !is_string($inputZone['size'])
                || !in_array($inputZone['size'], $allowedSizes)
            ) {
                throw new UnprocessableEntityHttpException('Invalid size.');
            }
        }
    }

    private function getAllowedSizes(Medium $medium): array
    {
        $sizes = [];
        foreach ($medium->getFormats() as $format) {
            $sizes = array_merge($sizes, $format->getScopes());
        }

        return array_keys($sizes);
    }

    private function validateFiltering($filtering): void
    {
        if (!is_array($filtering)) {
            throw new UnprocessableEntityHttpException('Invalid filtering');
        }
        foreach (['requires', 'excludes'] as $rootKey) {
            if (!is_array($filtering[$rootKey] ?? null)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid filtering %s', $rootKey));
            }
            $this->validateFilteringConditions($filtering[$rootKey], $rootKey);
        }
    }

    private function validateFilteringConditions(array $filteringConditions, string $rootKey): void
    {
        foreach ($filteringConditions as $key => $values) {
            if (!is_string($key)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid filtering %s class', $rootKey));
            }
            if (!is_array($values)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid filtering %s class value', $rootKey));
            }
            foreach ($values as $value) {
                if (!is_string($value)) {
                    throw new UnprocessableEntityHttpException(sprintf('Invalid filtering %s value', $rootKey));
                }
            }
        }
    }

    public function verifyDomain(Request $request): JsonResponse
    {
        $domain = $request->get('domain');
        $medium = $request->get('medium');
        $vendor = $request->get('vendor');
        if (null === $domain) {
            throw new BadRequestHttpException('Field `domain` is required.');
        }
        self::validateMedium($medium);
        self::validateVendor($vendor);
        self::validateDomain($domain, $medium, $vendor);

        return self::json(
            ['code' => Response::HTTP_OK, 'message' => 'Valid domain.'],
            Response::HTTP_OK
        );
    }

    private static function validateDomain(string $domain, string $medium, ?string $vendor): void
    {
        if (!SiteValidator::isDomainValid($domain)) {
            throw new UnprocessableEntityHttpException('Invalid domain.');
        }
        if (SitesRejectedDomain::isDomainRejected($domain)) {
            $rejectReasonId = SitesRejectedDomain::domainRejectedReasonId($domain);
            $message = sprintf('The domain %s is rejected.', $domain);
            if (null !== $rejectReasonId) {
                $reason = (new SiteRejectReason())->find($rejectReasonId)?->reject_reason;
                if (null === $reason) {
                    Log::warning(
                        sprintf('Cannot find reject reason (site_reject_reasons) with id (%d)', $rejectReasonId)
                    );
                } else {
                    $message = sprintf('%s Reason: %s', $message, $reason);
                }
            }
            throw new UnprocessableEntityHttpException($message);
        }
        if (MediumName::Metaverse->value === $medium) {
            try {
                MetaverseAddressValidator::fromVendor($vendor)->validateDomain($domain);
            } catch (InvalidArgumentException) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid domain %s.', $domain));
            }
        }
    }

    private static function validateMedium(mixed $medium): void
    {
        if (null === $medium) {
            throw new UnprocessableEntityHttpException('Field `medium` is required.');
        }
        if (!is_string($medium)) {
            throw new UnprocessableEntityHttpException('Field `medium` must be a string.');
        }
    }

    private static function validateVendor(mixed $vendor): void
    {
        if ($vendor !== null && !is_string($vendor)) {
            throw new UnprocessableEntityHttpException('Field `vendor` must be a string or null.');
        }
    }

    private function getBannerTypeBySize(Site $site): array
    {
        $formats = $this->configurationRepository->fetchMedium($site->medium, $site->vendor)->getFormats();
        $typeBySize = [];

        foreach ($formats as $format) {
            foreach ($format->getScopes() as $size => $label) {
                if (!isset($typeBySize[$size])) {
                    $typeBySize[$size] = $format->getType();
                }
            }
        }
        return $typeBySize;
    }

    private function getLabelBySize(Site $site): array
    {
        $formats = $this->configurationRepository->fetchMedium($site->medium, $site->vendor)->getFormats();
        $labelBySize = [];

        foreach ($formats as $format) {
            $labelBySize = array_merge($format->getScopes(), $labelBySize);
        }
        return $labelBySize;
    }
}

<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Services\Publisher;

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\CssUtils;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Supply\Domain\ValueObject\Size;

class SiteCodeGenerator
{
    private const CODE_TEMPLATE_DISPLAY
        = '<div class="{{selectorClass}}" {{dataOptions}}data-zone="{{zoneId}}" '
        . 'style="width:{{width}}px;height:{{height}}px;display: inline-block;margin: 0 auto">{{fallback}}</div>';

    private const CODE_TEMPLATE_POP
        = '<div class="{{selectorClass}}" {{dataOptions}}data-zone="{{zoneId}}" '
        . 'style="display: none">{{fallback}}</div>';

    public static function generateAsSingleString(Site $site, ?SiteCodeConfig $config = null): string
    {
        if (null === $config) {
            $config = SiteCodeConfig::default();
        }

        $commonCode = self::getCommonCode($config);

        $popsCodes = [];
        $displayCodes = [];
        foreach ($site->zones as $zone) {
            $zoneCode = self::getZoneCode($zone, $config);

            if (Size::TYPE_POP === $zone->type) {
                $popsCodes[] = "<!-- {$zone->name} -->\n" . $zoneCode;
            } else {
                $displayCodes[] = "<!-- {$zone->name} {$zone->size} -->\n" . $zoneCode;
            }
        }

        $code
            = <<<CODE
<!-- Common code for all ad units, should be inserted into head section -->
{$commonCode}
<!-- End of common code -->
CODE;

        if (count($popsCodes) > 0) {
            $popsCodes = join("\n\n", $popsCodes);
            $code
                .= <<<CODE


<!-- Code for pops -->
{$popsCodes}
<!-- End of code for pops-->
CODE;
        }

        if (count($displayCodes) > 0) {
            $displayCodes = join("\n\n", $displayCodes);
            $code
                .= <<<CODE


<!-- Code for ad units -->
{$displayCodes}
<!-- End of code for ad units -->
CODE;
        }

        return $code;
    }

    public static function generate(Site $site, ?SiteCodeConfig $config = null): array
    {
        if (null === $config) {
            $config = SiteCodeConfig::default();
        }

        $popsCodes = [];
        $displayCodes = [];
        foreach ($site->zones as $zone) {
            $zoneCode = self::getZoneCode($zone, $config);

            if (Size::TYPE_POP === $zone->type) {
                $popsCodes[] = [
                    'label' => $zone->name,
                    'code'  => $zoneCode,
                ];
            } else {
                $displayCodes[] = [
                    'label' => "{$zone->name} {$zone->size}",
                    'code'  => $zoneCode,
                ];
            }
        }

        return [
            'common'   => self::getCommonCode($config),
            'pops'     => $popsCodes,
            'ad_units' => $displayCodes,
        ];
    }

    public static function getCommonCode(?SiteCodeConfig $config = null): string
    {
        $proxyMainJs = $config !== null && $config->isUserResponsibleForMainJsProxy();
        if (config('app.main_js_tld') && !$proxyMainJs) {
            $params = [
                config('app.main_js_tld'),
                config('app.adserver_id'),
            ];
            $jsPath = public_path('-/main.js');

            return "<script type=\"text/javascript\">" . str_replace(
                [
                    '{{ TLD }}',
                    '{{ SELECTOR }}',
                ],
                $params,
                file_get_contents($jsPath)
            ) . "</script>";
        } else {
            $scriptUrl = $proxyMainJs ? '/main.js' : (new SecureUrl(route('supply-find.js')))->toString();

            return "<script type=\"text/javascript\" src=\"{$scriptUrl}\" async></script>";
        }
    }

    public static function getZoneCode(Zone $zone, ?SiteCodeConfig $config = null): string
    {
        if (Size::TYPE_POP === $zone->type) {
            return strtr(
                self::CODE_TEMPLATE_POP,
                [
                    '{{zoneId}}'        => $zone->uuid,
                    '{{selectorClass}}' => CssUtils::normalizeClass(config('app.adserver_id')),
                    '{{dataOptions}}'   => self::getDataOptionsForPops($config),
                    '{{fallback}}'      => self::getFallback($config),
                ]
            );
        }

        $size = Size::toDimensions($zone->size);

        $replaceArr = [
            '{{zoneId}}'        => $zone->uuid,
            '{{width}}'         => $size[0],
            '{{height}}'        => $size[1],
            '{{selectorClass}}' => CssUtils::normalizeClass(config('app.adserver_id')),
            '{{dataOptions}}'   => self::getDataOptions($config),
            '{{fallback}}'      => self::getFallback($config),
        ];

        return strtr(self::CODE_TEMPLATE_DISPLAY, $replaceArr);
    }

    private static function getDataOptions(?SiteCodeConfig $config): string
    {
        if (null === $config) {
            return '';
        }

        $options = [];
        if ($config->isAdBlockOnly()) {
            $options[] = 'adblock_only';
        }
        if (null !== $config->getMinCpm()) {
            $options[] = 'min_cpm=' . $config->getMinCpm();
        }

        if (null !== $config->getFallbackRate()) {
            $options[] = 'fallback_rate=' . $config->getFallbackRate();
        }

        if (empty($options)) {
            return '';
        }

        $dataOptions = join(',', $options);

        return "data-options=\"{$dataOptions}\" ";
    }

    private static function getDataOptionsForPops(?SiteCodeConfig $config): string
    {
        $siteCodeConfigPops = null === $config ? new SiteCodeConfigPops() : $config->getConfigPops();

        $options = [
            'count=' . $siteCodeConfigPops->getCount(),
            'interval=' . $siteCodeConfigPops->getInterval(),
            'burst=' . $siteCodeConfigPops->getBurst(),
        ];

        if (null !== $config) {
            if ($config->isAdBlockOnly()) {
                $options[] = 'adblock_only';
            }
            if (null !== $config->getMinCpm()) {
                $options[] = 'min_cpm=' . $config->getMinCpm();
            }
        }

        $dataOptions = join(',', $options);

        return "data-options=\"{$dataOptions}\" ";
    }

    private static function getFallback(?SiteCodeConfig $config): string
    {
        if (null === $config) {
            return '';
        }

        $options = [];
        if ($config->isCustomFallback()) {
            $options[] = "\t\tplace here custom fallback code";
        }
        if ($config->isAdBlockOnly()) {
            $options[] = "\t\tplace here code executed when ad blockers are not active";
        }
        if (null !== $config->getMinCpm()) {
            $options[] = "\t\tplace here code executed when minimum CPM requirement cannot be fulfilled";
        }

        if (empty($options)) {
            return '';
        }

        $fallback = join("\n", $options) . "\n";

        return "\n\t<style type=\"app/backfill\">\n{$fallback}\t</style>\n";
    }
}

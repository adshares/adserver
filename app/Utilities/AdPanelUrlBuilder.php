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

namespace Adshares\Adserver\Utilities;

class AdPanelUrlBuilder
{
    public static function buildAdvertiserDashboardUrl(): string
    {
        return config('app.adpanel_url') . '/advertiser/dashboard';
    }

    public static function buildCampaignUrl(int $campaignId): string
    {
        return config('app.adpanel_url') . '/advertiser/campaign/' . $campaignId;
    }

    public static function buildDepositUrl(): string
    {
        return config('app.adpanel_url') . '/settings/billing/wallet';
    }

    public static function buildPublisherDashboardUrl(): string
    {
        return config('app.adpanel_url') . '/publisher/dashboard';
    }

    public static function buildSiteUrl(int $siteId): string
    {
        return config('app.adpanel_url') . '/publisher/site/' . $siteId;
    }

    public static function buildUrl(): string
    {
        return config('app.adpanel_url');
    }
}

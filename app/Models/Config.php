<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    public const ADS_LOG_START = 'ads-log-start';

    public const ADPAY_CAMPAIGN_EXPORT_TIME = 'adpay-campaign-export';

    public const ADPAY_EVENT_EXPORT_TIME = 'adpay-event-export';

    private const ADSELECT_EVENT_EXPORT_TIME = 'adselect-event-export';

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function fetchAdSelectEventExportTime(): DateTime
    {
        $config = Config::where('key', self::ADSELECT_EVENT_EXPORT_TIME)->first();

        if (!$config) {
            return new DateTime('@0');
        }

        return DateTime::createFromFormat(DateTime::ATOM, $config->value);
    }

    public static function updateAdSelectEventExportTime(\DateTime $date): void
    {
        $config = Config::where('key', self::ADSELECT_EVENT_EXPORT_TIME)->first();

        if (!$config) {
            $config = new self();
            $config->key = self::ADSELECT_EVENT_EXPORT_TIME;
        }

        $config->value = $date->format(DateTime::ATOM);
        $config->save();
    }
}

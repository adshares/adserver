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

namespace Adshares\Adserver\Http\Response;

use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Supply\Application\Dto\Info;
use Illuminate\Contracts\Support\Arrayable;

final class InfoResponse implements Arrayable
{
    /** @var Info */
    private $info;

    public function __construct(Info $info)
    {
        $this->info = $info;
    }

    public function toArray(): array
    {
        $data = $this->info->toArray();
        $data['panel-base-url'] = $data['panelUrl'];
        $data['serviceVersion'] = $data['version'];
        $data['supported'] = $data['capabilities'];
        $data['serviceType'] = $data['module'];

        return $data;
    }

    public static function defaults(): self
    {
        return new self(new Info(
            (string)config('app.module'),
            (string)config('app.name'),
            (string)config('app.version'),
            new SecureUrl((string)config('app.url')),
            new Url((string)config('app.adpanel_url')),
            new SecureUrl((string)config('app.privacy_url')),
            new SecureUrl((string)config('app.terms_url')),
            new SecureUrl(route('demand-inventory')),
            Info::CAPABILITY_ADVERTISER,
            Info::CAPABILITY_PUBLISHER
        ));
    }
}

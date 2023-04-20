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

namespace Adshares\Adserver\Mail;

use Adshares\Adserver\Services\Common\AdsTxtCrawler;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Ramsey\Uuid\Uuid;

class SiteAdsTxtInvalid extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $publisherId,
        private readonly string $siteName,
        private readonly string $siteUrl,
    ) {
    }

    public function build(): self
    {
        $this->subject(sprintf("Invalid ads.txt file on %s", $this->siteName));
        return $this->markdown('emails.site-ads-txt-invalid')->with([
            'adsTxtEntry' => sprintf(
                '%s, %s, DIRECT',
                config('app.ads_txt_domain'),
                Uuid::fromString($this->publisherId)->toString(),
            ),
            'adsTxtUrl' => sprintf('%s/ads.txt', $this->siteUrl),
            'siteName' => $this->siteName,
        ]);
    }
}

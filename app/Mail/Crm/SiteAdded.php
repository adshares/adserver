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

namespace Adshares\Adserver\Mail\Crm;

use Adshares\Adserver\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SiteAdded extends Mailable
{
    use Queueable;
    use SerializesModels;

    private $email;

    private $site;

    private $userUuid;

    public function __construct(string $userUuid, string $email, Site $site)
    {
        $this->userUuid = $userUuid;
        $this->email = $email;
        $this->site = $site;
    }

    public function build(): self
    {
        $categories = (null !== $this->site->categories_by_user) ? join(', ', $this->site->categories_by_user) : '';

        return $this->view('emails.crm.site-added')->with(
            [
                'userUuid' => $this->userUuid,
                'email' => $this->email,
                'site' => $this->site,
                'categories' => $categories,
            ]
        );
    }
}

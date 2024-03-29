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

namespace Adshares\Adserver\Mail\Notifications;

use Adshares\Adserver\Utilities\AdPanelUrlBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InactiveUser extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function build(): Mailable
    {
        $this->subject = 'Get Started with Your First Campaign or Add Website';
        return $this->markdown('emails.notifications.inactive-user')
            ->with(
                [
                    'advertiserDashboardUrl' => AdPanelUrlBuilder::buildAdvertiserDashboardUrl(),
                    'bookingUrl' => config('app.booking_url'),
                    'contactEmail' => config('app.support_email'),
                    'publisherDashboardUrl' => AdPanelUrlBuilder::buildPublisherDashboardUrl(),
                ]
            );
    }
}

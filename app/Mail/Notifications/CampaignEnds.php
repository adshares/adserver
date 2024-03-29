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

class CampaignEnds extends Mailable
{
    use Queueable;
    use SerializesModels;

    protected $campaign;

    public function __construct($campaign)
    {
        $this->campaign = $campaign;
    }

    public function build(): Mailable
    {
        $this->subject = sprintf('Reminder: Campaign "%s" Ends in 3 Days', $this->campaign->name);
        return $this->markdown('emails.notifications.campaign-ends')
            ->with(
                [
                    'bookingUrl' => config('app.booking_url'),
                    'campaignName' => $this->campaign->name,
                    'campaignUrl' => AdPanelUrlBuilder::buildCampaignUrl($this->campaign->id),
                    'contactEmail' => config('app.support_email'),
                ]
            );
    }
}

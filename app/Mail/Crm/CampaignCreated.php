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

use Adshares\Adserver\Models\Campaign;
use DateTime;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampaignCreated extends Mailable
{
    use Queueable;
    use SerializesModels;

    private $campaign;

    private $email;

    private $userUuid;

    public function __construct(string $userUuid, string $email, Campaign $campaign)
    {
        $this->userUuid = $userUuid;
        $this->email = $email;
        $this->campaign = $campaign;
    }

    public function build(): self
    {
        $startDate = $this->changeDateFormat($this->campaign->time_start);
        $endDate = (null !== $this->campaign->time_end) ? $this->changeDateFormat($this->campaign->time_end) : '';
        $budgetPerDay = number_format($this->campaign->budget * 24 / 1e11, 2);

        return $this->view('emails.crm.campaign-created')->with(
            [
                'userUuid' => $this->userUuid,
                'email' => $this->email,
                'campaign' => $this->campaign,
                'budget' => $budgetPerDay,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]
        );
    }

    private function changeDateFormat(string $date): string
    {
        return DateTime::createFromFormat(DateTimeInterface::ATOM, $date)->format('d/m/Y H:i:s');
    }
}

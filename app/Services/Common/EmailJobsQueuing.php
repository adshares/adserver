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

namespace Adshares\Adserver\Services\Common;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Mail\Newsletter;
use Adshares\Adserver\Services\Common\Dto\EmailData;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailJobsQueuing
{
    private const PACKAGE_SIZE_MAX = 40;

    private const PACKAGE_INTERVAL = 120;

    public function addEmail(EmailData $email, Collection $recipients): void
    {
        $emailSendTime = new DateTime();

        DB::beginTransaction();

        try {
            $recipients->chunk(self::PACKAGE_SIZE_MAX)->each(
                function ($users) use ($emailSendTime, $email) {
                    foreach ($users as $user) {
                        Mail::to($user)->later(
                            $emailSendTime,
                            new Newsletter($email->getSubject(), $email->getBody(), $email->isAttachUnsubscribe())
                        );
                    }
                    $emailSendTime->modify(sprintf('+%d seconds', self::PACKAGE_INTERVAL));
                }
            );
        } catch (Throwable $throwable) {
            DB::rollBack();

            throw $throwable;
        }

        DB::commit();
    }
}

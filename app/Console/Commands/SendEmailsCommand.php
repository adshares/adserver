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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Mail\GeneralMessage;
use Adshares\Adserver\Models\Email;
use Adshares\Adserver\Models\User;
use DateTime;
use Illuminate\Support\Facades\Mail;

class SendEmailsCommand extends BaseCommand
{
    protected $signature = 'ops:email:send {email_id}';

    protected $description = 'Sends emails to all users';

    private const PACKAGE_SIZE_MAX = 40;

    private const PACKAGE_INTERVAL = 180;

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->signature.' already running');

            return;
        }

        $this->info('Start command '.$this->signature);

        $emailId = (int)$this->argument('email_id');
        $email = Email::fetchById($emailId);

        if (null === $email) {
            $this->info(sprintf('Cannot find email with id (%d)', $emailId));

            return;
        }

        $emailSendTime = new DateTime();
        User::chunk(
            self::PACKAGE_SIZE_MAX,
            function ($users) use ($emailSendTime, $email) {
                foreach ($users as $user) {
                    Mail::to($user)->later($emailSendTime, new GeneralMessage($email->subject, $email->body));
                }
                $emailSendTime->modify(sprintf('+%d seconds', self::PACKAGE_INTERVAL));
            }
        );

        $email->delete();

        $this->info('Finish command '.$this->signature);
    }
}

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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Mail\Newsletter;
use Adshares\Adserver\Models\Email;
use Adshares\Adserver\Models\User;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;
use function file;
use function is_readable;
use function trim;

class SendEmailsCommand extends BaseCommand
{
    protected $signature = 'ops:email:send
                            {email_id : Id of an email which will be sent}
                            {--d|dry-run : Lists recipients and message }
                            {--f|file-address-list= : File containing list of email addresses }
                            {--p|preview-address= : Email address for a preview }';

    protected $description = 'Sends emails';

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
            $this->info(sprintf('[SendEmailsCommand] Cannot find email with id (%d)', $emailId));

            return;
        }

        if (null !== ($previewAddress = $this->option('preview-address'))) {
            $recipients = collect($previewAddress);
        } else {
            if (null !== ($file = $this->option('file-address-list'))) {
                $recipients = $this->getAddressesFromFile($file);
            } else {
                $recipients = User::fetchEmails();
            }
        }

        if (0 === ($recipientsCount = $recipients->count())) {
            $this->info('[SendEmailsCommand] Recipients list is empty');

            return;
        }

        if ($this->option('dry-run')) {
            $this->info('[SendEmailsCommand] Recipients ->');
            foreach ($recipients as $recipient) {
                $this->info($recipient);
            }

            $this->info('[SendEmailsCommand] Message subject ->');
            $this->info($email->subject);
            $this->info('[SendEmailsCommand] Message body ->');
            $this->info($email->body);

            return;
        }

        $this->info(sprintf('[SendEmailsCommand] Sending emails to (%d) recipients', $recipientsCount));

        $this->addSendEmailJobsToQueue($email, $recipients);

        if (null === $previewAddress) {
            $email->delete();
        }

        $this->info('Finish command '.$this->signature);
    }

    private function getAddressesFromFile(string $file): Collection
    {
        if (!is_readable($file) || false === ($contents = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
            $this->info(sprintf('[SendEmailsCommand] File (%s) cannot be read', $file));

            return new Collection();
        }

        $results = array_map(
            function ($item) {
                return strtolower(trim(preg_replace('/\s+/', '', $item), '.'));
            },
            $contents
        );

        $results = array_values(
            array_filter(
                array_unique($results),
                function ($item) {
                    return false !== filter_var($item, FILTER_VALIDATE_EMAIL) && 1 !== preg_match('/@.*\.gov/', $item);
                }
            )
        );

        return collect($results);
    }

    private function addSendEmailJobsToQueue(Email $email, Collection $recipients): void
    {
        $emailSendTime = new DateTime();

        DB::beginTransaction();

        try {
            $recipients->chunk(self::PACKAGE_SIZE_MAX)->each(
                function ($users) use ($emailSendTime, $email) {
                    foreach ($users as $user) {
                        Mail::to($user)->later($emailSendTime, new Newsletter($email->subject, $email->body));
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

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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Email;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Common\Dto\EmailData;
use Adshares\Adserver\Services\Common\EmailJobsQueuing;
use Illuminate\Support\Collection;

use function file;
use function is_readable;
use function sprintf;
use function strpos;
use function substr;
use function trim;

class SendEmailsCommand extends BaseCommand
{
    protected $signature = 'ops:email:send
                            {--d|dry-run : Lists recipients and message }
                            {--i|email_id : Id of an email which will be sent}
                            {--f|file-address-list= : File containing list of email addresses }
                            {--m|file-message= : File containing email subject and body }
                            {--p|preview-address= : Email address for a preview }';

    protected $description = 'Sends emails';

    /** @var EmailJobsQueuing */
    private $emailJobsQueuing;

    public function __construct(EmailJobsQueuing $emailJobsQueuing, Locker $locker)
    {
        $this->emailJobsQueuing = $emailJobsQueuing;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('Start command ' . $this->signature);

        if (null === ($emailData = $this->getMessage())) {
            $this->info('[SendEmailsCommand] Cannot read email data.');

            return;
        }

        if (null !== ($previewAddress = $this->option('preview-address'))) {
            $recipients = collect($previewAddress);
        } else {
            if (null !== ($file = $this->option('file-address-list'))) {
                $recipients = $this->getAddressesFromFile($file);
            } else {
                $emailData->setAttachUnsubscribe(true);
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
            $this->info($emailData->getSubject());
            $this->info('[SendEmailsCommand] Message body ->');
            $this->info($emailData->getBody());

            return;
        }

        $this->info(sprintf('[SendEmailsCommand] Sending emails to (%d) recipients', $recipientsCount));

        $this->emailJobsQueuing->addEmail($emailData, $recipients);
        $this->deleteEmailFromDatabase();

        $this->info('Finish command ' . $this->signature);
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
                    return false !== filter_var($item, FILTER_VALIDATE_EMAIL)
                        && 1 !== preg_match('/@.*\.(edu|gov|mil)(\..*)?$/', $item);
                }
            )
        );

        return collect($results);
    }

    private function getMessage(): ?EmailData
    {
        if (null !== ($fileMessage = $this->option('file-message'))) {
            $emailData = $this->getMessageFromFile($fileMessage);
        } else {
            if (null !== ($emailId = $this->option('email_id'))) {
                $emailData = $this->getMessageFromDatabase((int)$emailId);
            } else {
                $this->info(sprintf('[SendEmailsCommand] Cannot read email - no id nor file provided'));
                $emailData = null;
            }
        }

        return $emailData;
    }

    private function getMessageFromFile(string $file): ?EmailData
    {
        if (!is_readable($file) || false === ($content = file_get_contents($file))) {
            $this->info(sprintf('[SendEmailsCommand] File (%s) cannot be read', $file));

            return null;
        }

        if (false === ($indexOfFirstNewLine = strpos($content, "\n"))) {
            $this->info(sprintf('[SendEmailsCommand] File (%s) does not have email body', $file));

            return null;
        }

        $subject = substr($content, 0, $indexOfFirstNewLine);
        $body = substr($content, $indexOfFirstNewLine + 1);

        return new EmailData($subject, $body);
    }

    private function getMessageFromDatabase(int $emailId): ?EmailData
    {
        if (null === ($email = Email::fetchById($emailId))) {
            $this->info(sprintf('[SendEmailsCommand] Cannot find email with id (%s)', $emailId));

            return null;
        }

        return new EmailData($email->subject, $email->body);
    }

    private function deleteEmailFromDatabase(): void
    {
        if (
            null === $this->option('preview-address') && null !== ($emailId = $this->option('email_id'))
            && null !== ($email = Email::fetchById((int)$emailId))
        ) {
            $email->delete();
        }
    }
}

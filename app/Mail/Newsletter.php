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

namespace Adshares\Adserver\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Newsletter extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 100;

    /** @var string */
    private $body;

    /** @var bool */
    private $attachUnsubscribe;

    public function __construct(string $subject, string $body, bool $attachUnsubscribe = false)
    {
        $this->subject($subject);
        $this->body = $body;
        $this->attachUnsubscribe = $attachUnsubscribe;
    }

    public function build()
    {
        $params = [
            'body' => $this->body,
        ];

        if ($this->attachUnsubscribe && null !== ($email = $this->extractEmail())) {
            $params['unsubscribe_url'] = $this->createUnsubscribeUrl($email);
        }

        return $this->markdown('emails.newsletter')->with(
            $params
        );
    }

    private function extractEmail(): ?string
    {
        if (1 !== count($this->to)) {
            return null;
        }

        return $this->to[0]['address'] ?? null;
    }

    private function createUnsubscribeUrl(string $emailAddress): string
    {
        return route(
            'newsletter-unsubscribe',
            [
                'address' => $emailAddress,
                'digest' => self::createDigest($emailAddress),
            ]
        );
    }

    public static function createDigest(string $emailAddress): string
    {
        return sha1($emailAddress . config('app.adserver_secret'));
    }
}

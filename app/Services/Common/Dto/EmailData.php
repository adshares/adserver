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

namespace Adshares\Adserver\Services\Common\Dto;

class EmailData
{
    /** @var string */
    private $subject;

    /** @var string */
    private $body;

    /** @var bool */
    private $attachUnsubscribe;

    public function __construct(string $subject, string $body, bool $attachUnsubscribe = false)
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->attachUnsubscribe = $attachUnsubscribe;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isAttachUnsubscribe(): bool
    {
        return $this->attachUnsubscribe;
    }

    public function setAttachUnsubscribe(bool $attachUnsubscribe): void
    {
        $this->attachUnsubscribe = $attachUnsubscribe;
    }
}

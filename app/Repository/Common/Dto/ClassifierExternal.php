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

namespace Adshares\Adserver\Repository\Common\Dto;

class ClassifierExternal
{
    /** @var string */
    private $name;

    /** @var string */
    private $publicKey;

    /** @var string */
    private $url;

    /** @var string */
    private $apiKeyName;

    /** @var string */
    private $apiKeySecret;

    public function __construct(string $name, string $publicKey, string $url, string $apiKeyName, string $apiKeySecret)
    {
        $this->name = $name;
        $this->publicKey = $publicKey;
        $this->url = $url;
        $this->apiKeyName = $apiKeyName;
        $this->apiKeySecret = $apiKeySecret;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getApiKeyName(): string
    {
        return $this->apiKeyName;
    }

    public function getApiKeySecret(): string
    {
        return $this->apiKeySecret;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

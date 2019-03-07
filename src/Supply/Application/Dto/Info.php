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

namespace Adshares\Supply\Application\Dto;

use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\UrlObject;

final class Info
{
    private const SUPPORTED_PUBLISHER = 'PUB';

    private const SUPPORTED_ADVERTISER = 'ADV';

    private const AVAILABLE_SUPPORTED_VALUES = [self::SUPPORTED_PUBLISHER, self::SUPPORTED_ADVERTISER];

    /** @var string */
    private $module;

    /** @var string */
    private $name;

    /** @var string */
    private $version;

    /** @var array */
    private $supported;

    /** @var string */
    private $panelUrl;

    /** @var string */
    private $privacyUrl;

    /** @var string */
    private $termsUrl;

    /** @var string */
    private $inventoryUrl;

    /** @var Url */
    private $serverUrl;

    public function __construct(
        string $module,
        string $name,
        string $version,
        UrlObject $serverUrl,
        UrlObject $panelUrl,
        UrlObject $privacyUrl,
        UrlObject $termsUrl,
        UrlObject $inventoryUrl,
        string ...$supported
    ) {
        $this->validateSupportedValue($supported);

        $this->module = $module;
        $this->name = $name;
        $this->version = $version;
        $this->supported = $supported;
        $this->panelUrl = $panelUrl;
        $this->privacyUrl = $privacyUrl;
        $this->termsUrl = $termsUrl;
        $this->inventoryUrl = $inventoryUrl;
        $this->serverUrl = $serverUrl;
    }

    public function validateSupportedValue(array $values): void
    {
        foreach ($values as $value) {
            if (!in_array($value, self::AVAILABLE_SUPPORTED_VALUES, true)) {
                throw new RuntimeException(sprintf('Given supported value %s is not correct.', $value));
            }
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['module'],
            $data['name'],
            $data['version'],
            new Url($data['serverUrl']),
            new Url($data['panelUrl']),
            new Url($data['privacyUrl']),
            new Url($data['termsUrl']),
            new Url($data['inventoryUrl']),
            ...$data['supported']
        );
    }

    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'name' => $this->name,
            'version' => $this->version,
            'supported' => $this->supported,
            'serverUrl' => $this->serverUrl->toString(),
            'panelUrl' => $this->panelUrl->toString(),
            'privacyUrl' => $this->privacyUrl->toString(),
            'termsUrl' => $this->termsUrl->toString(),
            'inventoryUrl' => $this->inventoryUrl->toString(),
            //BC for Wordpress Plugin
            $data['panel-base-url'] = $this->panelUrl->toString(),
        ];
    }

    public function getTermsUrl(): string
    {
        return $this->termsUrl->toString();
    }

    public function getPrivacyUrl(): string
    {
        return $this->privacyUrl->toString();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPanelUrl(): string
    {
        return $this->panelUrl->toString();
    }

    public function getServerUrl(): string
    {
        return $this->serverUrl->toString();
    }

    public function getInventoryUrl(): string
    {
        return $this->inventoryUrl->toString();
    }
}

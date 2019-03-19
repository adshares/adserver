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

use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\UrlInterface;

final class Info
{
    public const CAPABILITY_PUBLISHER = 'PUB';

    public const CAPABILITY_ADVERTISER = 'ADV';

    private const AVAILABLE_CAPABILITY_VALUES = [
        self::CAPABILITY_PUBLISHER,
        self::CAPABILITY_ADVERTISER,
    ];

    /** @var string */
    private $module;

    /** @var string */
    private $name;

    /** @var string */
    private $version;

    /** @var array */
    private $capabilities;

    /** @var UrlInterface */
    private $panelUrl;

    /** @var UrlInterface */
    private $privacyUrl;

    /** @var UrlInterface */
    private $termsUrl;

    /** @var UrlInterface */
    private $inventoryUrl;

    /** @var UrlInterface */
    private $serverUrl;

    /** @var Email|null */
    private $supportEmail;

    public function __construct(
        string $module,
        string $name,
        string $version,
        UrlInterface $serverUrl,
        UrlInterface $panelUrl,
        UrlInterface $privacyUrl,
        UrlInterface $termsUrl,
        UrlInterface $inventoryUrl,
        ?Email $supportEmail,
        string ...$capabilities
    ) {
        $this->validateCapabilities($capabilities);

        $this->module = $module;
        $this->name = $name;
        $this->version = $version;
        $this->capabilities = $capabilities;
        $this->panelUrl = $panelUrl;
        $this->privacyUrl = $privacyUrl;
        $this->termsUrl = $termsUrl;
        $this->inventoryUrl = $inventoryUrl;
        $this->serverUrl = $serverUrl;
        $this->supportEmail = $supportEmail;
    }

    public function validateCapabilities(array $values): void
    {
        foreach ($values as $value) {
            if (!in_array($value, self::AVAILABLE_CAPABILITY_VALUES, true)) {
                throw new RuntimeException(sprintf('Given supported value %s is not correct.', $value));
            }
        }
    }

    /** @deprecated Use object casting in NetworkHosts model */
    public static function fromArray(array $data): self
    {
        $email = isset($data['supportEmail']) ? new Email($data['supportEmail']) : null;

        return new self(
            $data['module'] ?? $data['module'],
            $data['name'],
            $data['version'] ?? $data['softwareVersion'],
            new Url($data['serverUrl']),
            new Url($data['panelUrl']),
            new Url($data['privacyUrl']),
            new Url($data['termsUrl']),
            new Url($data['inventoryUrl']),
            $email,
            ...$data['capabilities'] ?? $data['supported']
        );
    }

    public function toArray(): array
    {
        $data = [
            'module' => $this->module,
            'name' => $this->name,
            'version' => $this->version,
            'capabilities' => $this->capabilities,
            'serverUrl' => $this->serverUrl->toString(),
            'panelUrl' => $this->panelUrl->toString(),
            'privacyUrl' => $this->privacyUrl->toString(),
            'termsUrl' => $this->termsUrl->toString(),
            'inventoryUrl' => $this->inventoryUrl->toString(),
        ];

        if (null !== $this->supportEmail) {
            $data['supportEmail'] = $this->supportEmail->toString();
        }

        return $data;
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

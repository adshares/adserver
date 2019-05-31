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

    /** @var float|null */
    private $demandFee;

    /** @var float|null */
    private $supplyFee;

    /** @var InfoStatistics|null */
    private $statistics;

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

        $info = new self(
            $data['module'] ?? $data['serviceType'],
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

        if (isset($data['demandFee'])) {
            $info->setDemandFee($data['demandFee']);
        }

        if (isset($data['supplyFee'])) {
            $info->setSupplyFee($data['supplyFee']);
        }

        if (isset($data['statistics'])) {
            $info->setStatistics(InfoStatistics::fromArray($data['statistics']));
        }

        return $info;
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

        if (null !== $this->demandFee) {
            $data['demandFee'] = $this->demandFee;
        }

        if (null !== $this->supplyFee) {
            $data['supplyFee'] = $this->supplyFee;
        }

        if (null !== $this->statistics) {
            $data['statistics'] = $this->statistics->toArray();
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

    public function setDemandFee(float $demandFee): void
    {
        if (!in_array(self::CAPABILITY_ADVERTISER, $this->capabilities, true)) {
            throw new RuntimeException('Cannot set fee for unsupported capability: Advertiser');
        }

        $this->demandFee = $demandFee;
    }

    public function setSupplyFee(float $supplyFee): void
    {
        if (!in_array(self::CAPABILITY_PUBLISHER, $this->capabilities, true)) {
            throw new RuntimeException('Cannot set fee for unsupported capability: Publisher');
        }

        $this->supplyFee = $supplyFee;
    }

    public function setStatistics(InfoStatistics $statistics): void
    {
        $this->statistics = $statistics;
    }
}

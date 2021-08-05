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

namespace Adshares\Supply\Application\Dto;

use Adshares\Common\Domain\Id;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Domain\ValueObject\EmptyAccountId;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\UrlInterface;
use Adshares\Config\RegistrationMode;

final class Info
{
    public const CAPABILITY_PUBLISHER = 'PUB';

    public const CAPABILITY_ADVERTISER = 'ADV';

    private const AVAILABLE_CAPABILITY_VALUES = [
        self::CAPABILITY_PUBLISHER,
        self::CAPABILITY_ADVERTISER,
    ];

    private string $module;

    private string $name;

    private string $version;

    private array $capabilities;

    private UrlInterface $panelUrl;

    private UrlInterface $privacyUrl;

    private UrlInterface $termsUrl;

    private UrlInterface $inventoryUrl;

    private UrlInterface $serverUrl;

    private Id $adsAddress;

    private ?Email $supportEmail;

    private ?float $demandFee;

    private ?float $supplyFee;

    private string $registrationMode;

    private ?InfoStatistics $statistics;

    public function __construct(
        string $module,
        string $name,
        string $version,
        UrlInterface $serverUrl,
        UrlInterface $panelUrl,
        UrlInterface $privacyUrl,
        UrlInterface $termsUrl,
        UrlInterface $inventoryUrl,
        Id $adsAddress,
        ?Email $supportEmail,
        array $capabilities,
        string $registrationMode
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
        $this->adsAddress = $adsAddress;
        $this->supportEmail = $supportEmail;
        $this->registrationMode = $registrationMode;
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
        $adsAddress = isset($data['adsAddress']) ? new AccountId($data['adsAddress']) : new EmptyAccountId();

        $info = new self(
            $data['module'] ?? $data['serviceType'],
            $data['name'],
            $data['version'] ?? $data['softwareVersion'],
            new Url($data['serverUrl']),
            new Url($data['panelUrl']),
            new Url($data['privacyUrl']),
            new Url($data['termsUrl']),
            new Url($data['inventoryUrl']),
            $adsAddress,
            $email,
            $data['capabilities'] ?? $data['supported'],
            $data['registrationMode'] ?? RegistrationMode::PUBLIC
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
            'adsAddress' => $this->adsAddress->toString(),
            'registrationMode' => $this->registrationMode,
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

    public function getModule(): string
    {
        return $this->module;
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

    public function getAdsAddress(): string
    {
        return $this->adsAddress->toString();
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

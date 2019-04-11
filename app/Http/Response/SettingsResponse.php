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

namespace Adshares\Adserver\Http\Response;

use Adshares\Adserver\Models\Config;
use Adshares\Common\Domain\Id;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Domain\ValueObject\EmptyAccountId;
use Illuminate\Contracts\Support\Arrayable;

class SettingsResponse implements Arrayable
{
    private $advertiserCommission;

    private $publisherCommission;

    private $hotWalletMinValue;

    private $hotWalletMaxValue;

    private $coldWalletIsActive;

    private $adserverName;

    private $technicalEmail;

    private $supportEmail;

    private $coldWalletAddress;

    public function __construct(
        int $hotWalletMinValue,
        int $hotWalletMaxValue,
        string $adserverName,
        Email $technicalEmail,
        Email $supportEmail,
        int $coldWalletIsActive,
        Id $coldWalletAddress,
        ?Commission $advertiserCommission = null,
        ?Commission $publisherCommission = null
    ) {
        $this->hotWalletMinValue = $hotWalletMinValue;
        $this->hotWalletMaxValue = $hotWalletMaxValue;
        $this->adserverName = $adserverName;
        $this->technicalEmail = $technicalEmail;
        $this->supportEmail = $supportEmail;
        $this->advertiserCommission = $advertiserCommission;
        $this->publisherCommission = $publisherCommission;
        $this->coldWalletIsActive = $coldWalletIsActive;
        $this->coldWalletAddress = $coldWalletAddress;
    }

    public static function fromConfigModel(array $data): self
    {
        $publisherCommission = $data[Config::OPERATOR_RX_FEE] ?? null;
        $advertiserCommission = $data[Config::OPERATOR_TX_FEE] ?? null;
        $hotWalletMinValue = $data[Config::HOT_WALLET_MIN_VALUE];
        $hotWalletMaxValue = $data[Config::HOT_WALLET_MAX_VALUE];
        $coldWalletIsActive = $data[Config::COLD_WALLET_IS_ACTIVE];
        $coldWalletAddress = $data[Config::COLD_WALLET_ADDRESS];
        $adserverName = $data[Config::ADSERVER_NAME];
        $technicalEmail = $data[Config::TECHNICAL_EMAIL];
        $supportEmail = $data[Config::SUPPORT_EMAIL];

        return new self(
            (int)$hotWalletMinValue,
            (int)$hotWalletMaxValue,
            $adserverName,
            new Email($technicalEmail),
            new Email($supportEmail),
            (int)$coldWalletIsActive,
            $coldWalletAddress ? new AccountId((string)$coldWalletAddress) : new EmptyAccountId(),
            new Commission((float)$advertiserCommission),
            new Commission((float)$publisherCommission)
        );
    }

    public function toArray(): array
    {
        $data = [
            'cold_wallet_is_active' => $this->coldWalletIsActive,
            'cold_wallet_address' => $this->coldWalletAddress->toString(),
            'hotwallet_min_value' => $this->hotWalletMinValue,
            'hotwallet_max_value' => $this->hotWalletMaxValue,
            'adserver_name' => $this->adserverName,
            'technical_email' => $this->technicalEmail->toString(),
            'support_email' => $this->supportEmail->toString(),
            //TODO: remove when front done
            'hotwallet_is_active' => $this->coldWalletIsActive,
            'hotwallet_address' => $this->coldWalletAddress->toString(),
        ];

        if ($this->advertiserCommission) {
            $data['advertiser_commission'] = $this->advertiserCommission->getValue();
        }

        if ($this->publisherCommission) {
            $data['publisher_commission'] = $this->publisherCommission->getValue();
        }

        return ['settings' => $data];
    }
}

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
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\Id;
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
    private $adserverName;
    private $technicalEmail;
    private $supportEmail;
    private $address;

    public function __construct(
        int $hotWalletMinValue,
        int $hotWalletMaxValue,
        string $adserverName,
        Email $technicalEmail,
        Email $supportEmail,
        Id $address,
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
        $this->address = $address;
    }

    public static function fromConfigModel(array $data): self
    {
        $publisherCommission = $data[Config::OPERATOR_RX_FEE] ?? null;
        $advertiserCommission = $data[Config::OPERATOR_TX_FEE] ?? null;
        $hotWalletMinValue = $data[Config::HOT_WALLET_MIN_VALUE];
        $hotWalletMaxValue = $data[Config::HOT_WALLET_MAX_VALUE];
        $hotWalletAddress = $data[Config::HOT_WALLET_ADDRESS] ?? null;
        $adserverName = $data[Config::ADSERVER_NAME];
        $technicalEmail = $data[Config::TECHNICAL_EMAIL];
        $supportEmail = $data[Config::SUPPORT_EMAIL];

        return new self(
            (int)$hotWalletMinValue,
            (int)$hotWalletMaxValue,
            $adserverName,
            new Email($technicalEmail),
            new Email($supportEmail),
            $hotWalletAddress !== null ? new AccountId((string)$hotWalletAddress) : new EmptyAccountId(),
            new Commission((float)$advertiserCommission),
            new Commission((float)$publisherCommission)
        );
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $data = [
            'hotwalletMinValue' => $this->hotWalletMinValue,
            'hotwalletMaxValue' => $this->hotWalletMaxValue,
            'hotwalletAddress' => $this->address->toString(),
            'adserverName' => $this->adserverName,
            'technicalEmail' => $this->technicalEmail->toString(),
            'supportEmail' => $this->supportEmail->toString(),
        ];

        if ($this->advertiserCommission) {
            $data['advertiserCommission'] = $this->advertiserCommission->getValue();
        }

        if ($this->publisherCommission) {
            $data['publisherCommission'] = $this->publisherCommission->getValue();
        }

        return ['settings' => $data];
    }
}

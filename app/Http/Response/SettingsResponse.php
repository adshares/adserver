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

use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\Email;
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

    public function __construct(
        int $hotWalletMinValue,
        int $hotWalletMaxValue,
        string $adserverName,
        Email $technicalEmail,
        Email $supportEmail,
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
    }

    public static function fromConfigModel(array $data): self
    {
        $publisherCommission = $data['payment-rx-fee'] ?? null;
        $advertiserCommission = $data['payment-tx-fee'] ?? null;
        $hotWalletMinValue = $data['hotwallet-min-value'];
        $hotWalletMaxValue = $data['hotwallet-max-value'];
        $adserverName = $data['adserver-name'];
        $technicalEmail = $data['technical-email'];
        $supportEmail = $data['support-email'];

        return new self(
            (int) $hotWalletMinValue,
            (int) $hotWalletMaxValue,
            $adserverName,
            new Email($technicalEmail),
            new Email($supportEmail),
            new Commission((float) $advertiserCommission),
            new Commission((float) $publisherCommission)
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
            'hotWalletMinValue' => $this->hotWalletMinValue,
            'hotWalletMaxValue' => $this->hotWalletMaxValue,
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

        return $data;
    }
}

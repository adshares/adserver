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
    private int $hotWalletMinValue;

    private int $hotWalletMaxValue;

    private int $coldWalletIsActive;

    private string $adserverName;

    private Email $technicalEmail;

    private Email $supportEmail;

    private Id $coldWalletAddress;

    private int $referralRefundEnabled;

    private Commission $referralRefundCommission;

    private Commission $advertiserCommission;

    private Commission $publisherCommission;

    private string $registrationMode;

    private int $autoConfirmationEnabled;

    private string $aduserInfoUrl;

    public function __construct(
        int $hotWalletMinValue,
        int $hotWalletMaxValue,
        string $adserverName,
        Email $technicalEmail,
        Email $supportEmail,
        int $coldWalletIsActive,
        Id $coldWalletAddress,
        int $referralRefundEnabled,
        Commission $referralRefundCommission,
        Commission $advertiserCommission,
        Commission $publisherCommission,
        string $registrationMode,
        int $autoConfirmationEnabled,
        string $aduserInfoUrl
    ) {
        $this->hotWalletMinValue = $hotWalletMinValue;
        $this->hotWalletMaxValue = $hotWalletMaxValue;
        $this->adserverName = $adserverName;
        $this->technicalEmail = $technicalEmail;
        $this->supportEmail = $supportEmail;
        $this->coldWalletIsActive = $coldWalletIsActive;
        $this->coldWalletAddress = $coldWalletAddress;
        $this->referralRefundEnabled = $referralRefundEnabled;
        $this->referralRefundCommission = $referralRefundCommission;
        $this->advertiserCommission = $advertiserCommission;
        $this->publisherCommission = $publisherCommission;
        $this->registrationMode = $registrationMode;
        $this->autoConfirmationEnabled = $autoConfirmationEnabled;
        $this->aduserInfoUrl = $aduserInfoUrl;
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
        $referralRefundEnabled = $data[Config::REFERRAL_REFUND_ENABLED];
        $referralRefundCommission = $data[Config::REFERRAL_REFUND_COMMISSION];
        $registrationMode = $data[Config::REGISTRATION_MODE];
        $autoConfirmationEnabled = $data[Config::AUTO_CONFIRMATION_ENABLED];
        $aduserInfoUrl = config('app.aduser_info_url');

        return new self(
            (int)$hotWalletMinValue,
            (int)$hotWalletMaxValue,
            $adserverName,
            new Email($technicalEmail),
            new Email($supportEmail),
            (int)$coldWalletIsActive,
            $coldWalletAddress ? new AccountId((string)$coldWalletAddress) : new EmptyAccountId(),
            (int)$referralRefundEnabled,
            new Commission((float)$referralRefundCommission),
            new Commission((float)$advertiserCommission),
            new Commission((float)$publisherCommission),
            $registrationMode,
            (int)$autoConfirmationEnabled,
            $aduserInfoUrl
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
            'referral_refund_enabled' => $this->referralRefundEnabled,
            'referral_refund_commission' => $this->referralRefundCommission->getValue(),
            'advertiser_commission' => $this->advertiserCommission->getValue(),
            'publisher_commission' => $this->publisherCommission->getValue(),
            'registration_mode' => $this->registrationMode,
            'auto_confirmation_enabled' => $this->autoConfirmationEnabled,
            'aduser_info_url' => $this->aduserInfoUrl,
        ];

        return ['settings' => $data];
    }
}

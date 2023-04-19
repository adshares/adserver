<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Common\Infrastructure\Service;

use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;

class LicenseReader
{
    private const DEFAULT_FEE = 0.0;
    private const LICENSE_ACCOUNT = 'licence-account';
    public const LICENSE_RX_FEE = 'licence-rx-fee';
    public const LICENSE_TX_FEE = 'licence-tx-fee';

    private LicenseVault $licenseVault;

    public function __construct(LicenseVault $licenseVault)
    {
        $this->licenseVault = $licenseVault;
    }

    public function getAddress(): ?AccountId
    {
        $value = apcu_fetch(self::LICENSE_ACCOUNT);

        if ($value) {
            return new AccountId($value);
        }

        try {
            $license = $this->licenseVault->read();
        } catch (RuntimeException $exception) {
            Log::error(sprintf('License read failed: %s', $exception->getMessage()));
            return null;
        }

        $value = $license->getPaymentAddress();

        apcu_store(self::LICENSE_ACCOUNT, $value->toString());

        return $value;
    }

    public function getFee(string $type): float
    {
        if (!in_array($type, [self::LICENSE_RX_FEE, self::LICENSE_TX_FEE], true)) {
            throw new RuntimeException(sprintf('Unsupported fee (%s) type', $type));
        }

        $value = apcu_fetch($type);

        if ($value) {
            return $value;
        }

        try {
            $license = $this->licenseVault->read();
        } catch (RuntimeException $exception) {
            Log::error(sprintf('License read failed: %s', $exception->getMessage()));
            return self::DEFAULT_FEE;
        }

        if (self::LICENSE_TX_FEE === $type) {
            $value = $license->getDemandFee();
        } elseif (self::LICENSE_RX_FEE === $type) {
            $value = $license->getSupplyFee();
        }

        apcu_store($type, $value);

        return $value;
    }

    public function getInfoBox(): bool
    {
        try {
            $license = $this->licenseVault->read();
            $value = $license->getInfoBox();
        } catch (RuntimeException) {
            $value = true;
        }
        return $value;
    }
}

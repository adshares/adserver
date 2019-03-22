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

namespace Adshares\Common\Infrastructure\Service;

use Adshares\Adserver\Models\Config;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Exception\RuntimeException;
use function apcu_fetch;

class LicenseFeeReader
{
    private const LICENCE_TX_FEE = 'licence-tx-fee';
    private const LICENCE_RX_FEE = 'licence-rx-fee';

    /** @var LicenseVault */
    private $licenseVault;

    public function __construct(LicenseVault $licenseVault)
    {
        $this->licenseVault = $licenseVault;
    }

    public function getFee(string $type): float
    {
        if (!in_array($type, [self::LICENCE_RX_FEE, self::LICENCE_TX_FEE], true)) {
            throw new RuntimeException(sprintf('Unsupported fee (%s) type', $type));
        }

        $value = apcu_fetch($type);

        if ($value) {
            return $value;
        }

        try {
            $license = $this->licenseVault->read();
        } catch (RuntimeException $exception) {
            return Config::getFee($type); // default fees are fetched from DB
        }

        if ($type === self::LICENCE_TX_FEE) {
            $value = $license->getDemandFee();
        } else if ($type === self::LICENCE_RX_FEE) {
            $value = $license->getSupplyFee();
        }

        apcu_store($type, $value);

        return $value;
    }
}

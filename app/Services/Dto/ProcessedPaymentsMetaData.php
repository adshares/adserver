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

namespace Adshares\Adserver\Services\Dto;

final class ProcessedPaymentsMetaData
{
    public function __construct(
        private readonly array $paymentIds,
        private readonly int $processedPaymentsTotal,
        private readonly int $processedPaymentsForAds,
    ) {
    }

    public function getPaymentIds(): array
    {
        return $this->paymentIds;
    }

    public function getProcessedPaymentsTotal(): int
    {
        return $this->processedPaymentsTotal;
    }

    public function getProcessedPaymentsForAds(): int
    {
        return $this->processedPaymentsForAds;
    }
}

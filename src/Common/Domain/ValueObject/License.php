<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Common\Domain\ValueObject;

use DateTimeInterface;

class License
{
    private string $id;
    private string $type;
    private int $status;
    private DateTimeInterface $dateStart;
    private DateTimeInterface $dateEnd;
    private string $owner;
    private AccountId $paymentAddress;
    private Commission $fixedFee;
    private Commission $demandFee;
    private Commission $supplyFee;
    private bool $infoBox;

    public function __construct(
        string $id,
        string $type,
        int $status,
        DateTimeInterface $dateStart,
        DateTimeInterface $dateEnd,
        string $owner,
        AccountId $paymentAddress,
        Commission $fixedFee,
        Commission $demandFee,
        Commission $supplyFee,
        bool $infoBox
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->status = $status;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
        $this->owner = $owner;
        $this->paymentAddress = $paymentAddress;
        $this->fixedFee = $fixedFee;
        $this->demandFee = $demandFee;
        $this->supplyFee = $supplyFee;
        $this->infoBox = $infoBox;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'dateStart' => $this->dateStart->format(DateTimeInterface::ATOM),
            'dateEnd' => $this->dateEnd->format(DateTimeInterface::ATOM),
            'owner' => $this->owner,
            'paymentAddress' => $this->paymentAddress->toString(),
            'fixedFee' => $this->fixedFee->getValue(),
            'demandFee' => $this->demandFee->getValue(),
            'supplyFee' => $this->supplyFee->getValue(),
            'infoBox' => $this->infoBox,
        ];
    }

    public function getDemandFee(): float
    {
        return $this->demandFee->getValue();
    }

    public function getSupplyFee(): float
    {
        return $this->supplyFee->getValue();
    }

    public function getPaymentAddress(): AccountId
    {
        return $this->paymentAddress;
    }

    public function getInfoBox(): bool
    {
        return $this->infoBox;
    }
}

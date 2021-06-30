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

namespace Adshares\Common\Domain\ValueObject;

use DateTime;
use DateTimeInterface;

class License
{
    /** @var string */
    private $id;
    /** @var string */
    private $type;
    /** @var int */
    private $status;
    /** @var DateTime */
    private $dateStart;
    /** @var DateTime */
    private $dateEnd;
    /** @var string */
    private $owner;
    /** @var AccountId */
    private $paymentAddress;
    /** @var Commission */
    private $fixedFee;
    /** @var Commission */
    private $demandFee;
    /** @var Commission */
    private $supplyFee;

    public function __construct(
        string $id,
        string $type,
        int $status,
        DateTime $dateStart,
        DateTime $dateEnd,
        string $owner,
        AccountId $paymentAddress,
        Commission $fixedFee,
        Commission $demandFee,
        Commission $supplyFee
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
}

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

namespace Adshares\Common\Domain\ValueObject;

use DateTime;

class License
{
    /** @var string */
    private $type;
    /** @var string */
    private $status;
    /** @var DateTime */
    private $dateStart;
    /** @var DateTime */
    private $dateEnd;
    /** @var string */
    private $owner;
    /** @var AccountId */
    private $paymentAddress;
    /** @var string */
    private $paymentMessage;
    /** @var Commission */
    private $fixedFee;
    /** @var Commission */
    private $demandFee;
    /** @var Commission */
    private $supplyFee;

    public function __construct(
        string $type,
        string $status,
        DateTime $dateStart,
        DateTime $dateEnd,
        string $owner,
        AccountId $paymentAddress,
        string $paymentMessage,
        Commission $fixedFee,
        Commission $demandFee,
        Commission $supplyFee
    )
    {
        $this->type = $type;
        $this->status = $status;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
        $this->owner = $owner;
        $this->paymentAddress = $paymentAddress;
        $this->paymentMessage = $paymentMessage;
        $this->fixedFee = $fixedFee;
        $this->demandFee = $demandFee;
        $this->supplyFee = $supplyFee;
    }
}

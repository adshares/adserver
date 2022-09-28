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

namespace Adshares\Adserver\Tests\Mail;

use Adshares\Adserver\Mail\DepositProcessed;
use Adshares\Common\Application\Model\Currency;

class DepositProcessedTest extends MailTestCase
{
    /**
     * @dataProvider currencyProvider
     */
    public function testBuild(Currency $currency, $expectedAmount): void
    {
        $mailable = new DepositProcessed(12_345_678_900_000, $currency);

        $mailable->assertSeeInText($expectedAmount);
    }

    public function currencyProvider(): array
    {
        return [
            'ADS' => [Currency::ADS, '123.45678900000 ADS'],
            'USD' => [Currency::USD, '123.45 USD'],
        ];
    }
}

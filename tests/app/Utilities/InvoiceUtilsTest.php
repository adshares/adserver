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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\InvoiceUtils;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class InvoiceUtilsTest extends TestCase
{
    public function testVatRates(): void
    {
        // Polnad
        $this->assertEquals(['23%' => 0.23], InvoiceUtils::getVatRate('PL', true));
        $this->assertEquals(['23%' => 0.23], InvoiceUtils::getVatRate('PL', false));

        // EU
        $this->assertEquals(['np. EU' => 0], InvoiceUtils::getVatRate('DE', true));
        $this->assertEquals(['23%' => 0.23], InvoiceUtils::getVatRate('DE', false));

        // XI Northern Ireland
        $this->assertEquals(['np. EU' => 0], InvoiceUtils::getVatRate('XI', true));
        $this->assertEquals(['23%' => 0.23], InvoiceUtils::getVatRate('XI', false));

        // None EU
        $this->assertEquals(['np.' => 0], InvoiceUtils::getVatRate('GH', true));
        $this->assertEquals(['np.' => 0], InvoiceUtils::getVatRate('GH', false));

        // Unknown
        $this->assertEquals(['np.' => 0], InvoiceUtils::getVatRate('XX', true));
        $this->assertEquals(['np.' => 0], InvoiceUtils::getVatRate('XX', false));
    }

    public function testFormatNumber(): void
    {
        $date = new Carbon('2021-08-05 14:37:12');

        $this->assertEquals('PROF 0001/08/2021', InvoiceUtils::formatNumber('PROF NNNN/MM/YYYY', 1, $date));
        $this->assertEquals('PROF 0123/08/2021', InvoiceUtils::formatNumber('PROF NNNN/MM/YYYY', 123, $date));
        $this->assertEquals('PROF 1234/08/2021', InvoiceUtils::formatNumber('PROF NNNN/MM/YYYY', 1234, $date));
        $this->assertEquals('PROF 1234/08/2021', InvoiceUtils::formatNumber('PROF NNNN/MM/YYYY', 12345, $date));

        $this->assertEquals('00001/08/2021', InvoiceUtils::formatNumber('NNNNN/MM/YYYY', 1, $date));
        $this->assertEquals('0001/08/2021', InvoiceUtils::formatNumber('NNNN/MM/YYYY', 1, $date));
        $this->assertEquals('001/08/2021', InvoiceUtils::formatNumber('NNN/MM/YYYY', 1, $date));
        $this->assertEquals('01/08/2021', InvoiceUtils::formatNumber('NN/MM/YYYY', 1, $date));
        $this->assertEquals('1/08/2021', InvoiceUtils::formatNumber('N/MM/YYYY', 1, $date));
        $this->assertEquals('0001/08/21', InvoiceUtils::formatNumber('NNNN/MM/YY', 1, $date));
        $this->assertEquals('21-08-01', InvoiceUtils::formatNumber('YY-MM-NN', 1, $date));
        $this->assertEquals('2021-08-01', InvoiceUtils::formatNumber('YYYY-MM-NN', 1, $date));

        $this->assertEquals('a/01/08/21', InvoiceUtils::formatNumber('A/NN/MM/YY', 1, $date, 'abc123'));
        $this->assertEquals('ab/01/08/21', InvoiceUtils::formatNumber('AA/NN/MM/YY', 1, $date, 'abc123'));
        $this->assertEquals('abc/01/08/21', InvoiceUtils::formatNumber('AAA/NN/MM/YY', 1, $date, 'abc123'));
        $this->assertEquals('abc1/01/08/21', InvoiceUtils::formatNumber('AAAA/NN/MM/YY', 1, $date, 'abc123'));
        $this->assertEquals('abc12/01/08/21', InvoiceUtils::formatNumber('AAAAA/NN/MM/YY', 1, $date, 'abc123'));
        $this->assertEquals('abc123/01/08/21', InvoiceUtils::formatNumber('AAAAAA/NN/MM/YY', 1, $date, 'abc123'));
        $this->assertEquals('0abc123/01/08/21', InvoiceUtils::formatNumber('AAAAAAA/NN/MM/YY', 1, $date, 'abc123'));

        $this->assertEquals('PROF\0001\08\21', InvoiceUtils::formatNumber('PROF\\\\NNNN\\\\MM\\\\YY', 1, $date));
        $this->assertEquals('PROF N001/M08/Y21', InvoiceUtils::formatNumber('PROF \NNNN/\MMM/\YYY', 1, $date));
        $this->assertEquals('PROF N001\M08\Y21', InvoiceUtils::formatNumber('PROF \NNNN\\\\\MMM\\\\\YYY', 1, $date));
    }
}

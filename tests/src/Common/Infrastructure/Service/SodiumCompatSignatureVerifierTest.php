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

namespace Adshares\Tests\Common\Infrastructure\Service;

use Adshares\Common\Infrastructure\Service\SodiumCompatSignatureVerifier;
use DateTime;
use PHPUnit\Framework\TestCase;

class SodiumCompatSignatureVerifierTest extends TestCase
{
    private const PRIVATE_KEY = 'CB5A6B541436A904BCFEE0CCE2D2B207977012492A035C5455027D5E48176EE1';
    private const PUBLIC_KEY = 'A25FFEE788D6E06D2DA70B48E44BCA0ABC5E5BEB16252B3E022C6C7B971EECC2';

    public function testIfCreatedSignatureIsCorrect(): void
    {
        $signatureService = new SodiumCompatSignatureVerifier();

        $transactionId = '0003:00000001:0011';
        $accountId = '0003-00000007-AF0B';
        $date = new DateTime();

        $signature = $signatureService->create(self::PRIVATE_KEY, $transactionId, $accountId, $date);
        $isVerified = $signatureService->verify(self::PUBLIC_KEY, $signature, $transactionId, $accountId, $date);

        $this->assertTrue($isVerified);
    }
}

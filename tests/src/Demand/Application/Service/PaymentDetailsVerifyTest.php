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

namespace Adshares\Tests\Demand\Application\Service;

use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use DateTime;
use PHPUnit\Framework\TestCase;

final class PaymentDetailsVerifyTest extends TestCase
{
    public function testVerify(): void
    {
        $publicKey = '00';
        $signature = '11';
        $transactionId = '22';
        $accountAddress = '33';
        $date = new DateTime();
        $verifier = self::createMock(SignatureVerifier::class);
        $verifier->expects(self::once())
            ->method('verifyTransactionId')
            ->with($publicKey, $signature, $transactionId, $accountAddress, $date)
            ->willReturn(true);
        $adsClient = self::createMock(Ads::class);
        $adsClient->expects(self::once())
            ->method('getPublicKeyByAccountAddress')
            ->with($accountAddress)
            ->willReturn($publicKey);

        $paymentDetailsVerify = new PaymentDetailsVerify($verifier, $adsClient);
        $paymentDetailsVerify->verify($signature, $transactionId, $accountAddress, $date);
    }
}

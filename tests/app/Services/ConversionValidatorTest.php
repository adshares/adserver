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

namespace Adshares\Adserver\Tests\Services;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Services\ConversionValidator;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

final class ConversionValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function testConversionValidatorValidSignature(): void
    {
        $conversionUuid = '5F14A43205CC4C81A63153B71547D143';
        $nonce = '123456';
        $timestampCreated = time();
        $value = '10.26';
        $secret = 'gKH9a3UDEGn7F71NOWfuvw';

        $signature = $this->generateSignature($conversionUuid, $nonce, $timestampCreated, $value, $secret);

        $conversionValidator = new ConversionValidator();

        $isSignatureValid = $conversionValidator->validateSignature(
            $signature,
            $conversionUuid,
            $nonce,
            $timestampCreated,
            $value,
            $secret
        );

        $this->assertTrue($isSignatureValid);
    }

    public function testConversionValidatorInvalidSignature(): void
    {
        $conversionUuid = '5F14A43205CC4C81A63153B71547D143';
        $nonce = '123456';
        $timestampCreated = 0;
        $value = '10.26';
        $secret = 'gKH9a3UDEGn7F71NOWfuvw';

        $signature = 'loremipsum';

        $conversionValidator = new ConversionValidator();

        $isSignatureValid = $conversionValidator->validateSignature(
            $signature,
            $conversionUuid,
            $nonce,
            $timestampCreated,
            $value,
            $secret
        );

        $this->assertFalse($isSignatureValid);
    }

    public function testConversionValidatorInvalidTimestamp(): void
    {
        $conversionUuid = '5F14A43205CC4C81A63153B71547D143';
        $nonce = '123456';
        $timestampCreated = 0;
        $value = '10.26';
        $secret = 'gKH9a3UDEGn7F71NOWfuvw';

        $signature = $this->generateSignature($conversionUuid, $nonce, $timestampCreated, $value, $secret);

        $conversionValidator = new ConversionValidator();

        $isSignatureValid = $conversionValidator->validateSignature(
            $signature,
            $conversionUuid,
            $nonce,
            $timestampCreated,
            $value,
            $secret
        );

        $this->assertFalse($isSignatureValid);
    }

    public function testConversionValidatorRepeatedNonce(): void
    {
        $conversionUuid = '5F14A43205CC4C81A63153B71547D143';
        $nonce = '123456';
        $timestampCreated = time();
        $value = '10.26';
        $secret = 'gKH9a3UDEGn7F71NOWfuvw';

        $signature = $this->generateSignature($conversionUuid, $nonce, $timestampCreated, $value, $secret);

        $conversionValidator = new ConversionValidator();

        $conversionValidator->validateSignature(
            $signature,
            $conversionUuid,
            $nonce,
            $timestampCreated,
            $value,
            $secret
        );

        $this->expectException('RuntimeException');

        $conversionValidator->validateSignature(
            $signature,
            $conversionUuid,
            $nonce,
            $timestampCreated,
            $value,
            $secret
        );
    }

    private function generateSignature(
        string $conversionUuid,
        string $nonce,
        int $timestampCreated,
        string $value,
        string $secret
    ): string {
        return Utils::urlSafeBase64Encode(
            hash(
                'sha256',
                $conversionUuid . Utils::urlSafeBase64Decode($nonce) . $timestampCreated . $value . $secret,
                true
            )
        );
    }
}

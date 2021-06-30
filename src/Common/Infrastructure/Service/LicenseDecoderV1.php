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

namespace Adshares\Common\Infrastructure\Service;

use Adshares\Common\Application\Service\LicenseDecoder;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\License;
use Adshares\Common\Exception\RuntimeException;
use DateTime;
use DateTimeInterface;

use function base64_decode;
use function hash_equals;
use function hash_hmac;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function substr;

class LicenseDecoderV1 implements LicenseDecoder
{
    private const METHOD = 'AES-128-CBC';
    /** @var string */
    private $licenseKey;

    public function __construct(string $licenseKey)
    {
        $this->licenseKey = $licenseKey;
    }

    public function decode(string $encodedLicense): License
    {
        $raw = base64_decode($encodedLicense);

        $ivlen = openssl_cipher_iv_length(self::METHOD);
        $iv = substr($raw, 0, $ivlen);
        $hmac = substr($raw, $ivlen, $sha2len = 32);

        $encrypted = substr($raw, $ivlen + $sha2len);

        $data = openssl_decrypt(
            $encrypted,
            self::METHOD,
            $this->licenseKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        $calcmac = hash_hmac('sha256', $encrypted, $this->licenseKey, true);

        if (!hash_equals($hmac, $calcmac)) {
            throw new RuntimeException(sprintf('Wrong licenseKey (%s).', $this->licenseKey));
        }

        $data = json_decode($data, true);

        return new License(
            (string)config('app.license_id'),
            $data['type'],
            $data['status'],
            DateTime::createFromFormat(DateTimeInterface::ATOM, $data['beginDate']),
            DateTime::createFromFormat(DateTimeInterface::ATOM, $data['endDate']),
            $data['owner'],
            new AccountId($data['paymentAddress']),
            new Commission($data['fixedFee']),
            new Commission($data['demandFee']),
            new Commission($data['supplyFee'])
        );
    }
}

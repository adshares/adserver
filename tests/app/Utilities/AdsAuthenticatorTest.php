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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Common\Application\Service\SignatureVerifier;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AdsAuthenticatorTest extends TestCase
{
    private const ADS_PK = 'CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB';

    public function testAuthentication(): void
    {
        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);

        $header = $authenticator->getHeader('0001-00000001-8B4E', self::ADS_PK);

        $request = new Request();
        $request->headers->set('Authorization', $header);
        $account = $authenticator->verifyRequest($request);
        $this->assertEquals('0001-00000001-8B4E', $account);
    }

    public function testNoHeader(): void
    {
        $this->expectException(UnauthorizedHttpException::class);
        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);
        $authenticator->verifyRequest(new Request());
    }

    public function testInvalidHeader(): void
    {
        $this->expectException(UnauthorizedHttpException::class);
        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);
        $header = 'ADS 3cf046fc1cbe584ce4cf0a3b3d361e90ade30763023cf755ee31b71a5c570e"';
        $request = new Request();
        $request->headers->set('Authorization', $header);
        $authenticator->verifyRequest($request);
    }

    public function testInvalidAccount(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid account');

        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);
        $header = $this->dummyHeader(
            'FOO',
            'AjRiNmNjN2U3YThiMTg0YQ==',
            new DateTimeImmutable(),
            self::ADS_PK
        );
        $request = new Request();
        $request->headers->set('Authorization', substr($header, 0, strlen($header) - 2) . '"');
        $authenticator->verifyRequest($request);
    }

    public function testInvalidDate(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid date');

        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $header = 'ADS account="0001-00000001-8B4E", nonce="NjRiNmNjN2U3YThiMTg0YQ==", created="foo", signature="d1fca407938483b6afb0561fc11ef4d38d72892b7b2f6ac98166cc2bb9d775dfa33cf046fc1cbe584ce4cf0a3b3d361e90ade30763023cf755ee31b71a5c570e"';
        $request = new Request();
        $request->headers->set('Authorization', $header);
        $authenticator->verifyRequest($request);
    }

    public function testInvalidDateFromThePast(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Expired token');

        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);
        $header = $this->dummyHeader(
            '0001-00000001-8B4E',
            'AjRiNmNjN2U3YThiMTg0YQ==',
            new DateTimeImmutable('-1 day'),
            self::ADS_PK
        );
        $request = new Request();
        $request->headers->set('Authorization', $header);
        $authenticator->verifyRequest($request);
    }

    public function testInvalidDateFromTheFuture(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Date from the future');

        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);
        $header = $this->dummyHeader(
            '0001-00000001-8B4E',
            'AjRiNmNjN2U3YThiMTg0YQ==',
            new DateTimeImmutable('+1 day'),
            self::ADS_PK
        );
        $request = new Request();
        $request->headers->set('Authorization', $header);
        $authenticator->verifyRequest($request);
    }

    public function testInvalidNonce(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Previously used nonce detected');

        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);

        $header = $this->dummyHeader(
            '0001-00000001-8B4E',
            'AjRiNmNjN2U3YThiMTg0YQ==',
            new DateTimeImmutable(),
            self::ADS_PK
        );
        $request = new Request();
        $request->headers->set('Authorization', $header);
        $authenticator->verifyRequest($request);

        $header = $this->dummyHeader(
            '0001-00000001-8B4E',
            'AjRiNmNjN2U3YThiMTg0YQ==',
            new DateTimeImmutable(),
            self::ADS_PK
        );
        $request = new Request();
        $request->headers->set('Authorization', $header);
        $authenticator->verifyRequest($request);
    }

    public function testInvalidSignature(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid signature');

        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);
        $header = $this->dummyHeader(
            '0001-00000001-8B4E',
            'AjRiNmNjN2U3YThiMTg0YQ==',
            new DateTimeImmutable(),
            self::ADS_PK
        );
        $request = new Request();
        $request->headers->set('Authorization', substr($header, 0, strlen($header) - 2) . '"');
        $authenticator->verifyRequest($request);
    }

    private function dummyHeader(string $account, string $nonce, DateTimeInterface $created, string $privateKey): string
    {
        /** @var SignatureVerifier $signatureVerifier */
        $signatureVerifier = $this->app->make(SignatureVerifier::class);
        $signature = $signatureVerifier->createFromNonce($privateKey, $nonce, $created);

        return sprintf(
            'ADS account="%s", nonce="%s", created="%s", signature="%s"',
            $account,
            $nonce,
            $created->format('c'),
            $signature
        );
    }
}

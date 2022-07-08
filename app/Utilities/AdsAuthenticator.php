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

namespace Adshares\Adserver\Utilities;

use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\Exception\AdsException;
use Adshares\Common\Application\Service\Exception\SignatureVerifierException;
use Adshares\Common\Application\Service\SignatureVerifier;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AdsAuthenticator
{
    private const LIFETIME = 300;

    private SignatureVerifier $signatureVerifier;
    private Ads $adsClient;
    private LoggerInterface $logger;

    public function __construct(SignatureVerifier $signatureVerifier, Ads $adsClient, LoggerInterface $logger = null)
    {
        if (null === $logger) {
            $logger = new NullLogger();
        }
        $this->signatureVerifier = $signatureVerifier;
        $this->adsClient = $adsClient;
        $this->logger = $logger;
    }

    public function getHeader(string $account, string $privateKey): string
    {
        $nonce = base64_encode(NonceGenerator::get());
        $created = new DateTimeImmutable();
        $signature = $this->signatureVerifier->createFromNonce($privateKey, $nonce, $created);

        return sprintf(
            'ADS account="%s", nonce="%s", created="%s", signature="%s"',
            $account,
            $nonce,
            $created->format('c'),
            $signature
        );
    }

    public function verifyRequest(Request $request): string
    {
        $this->logger->debug(sprintf('Authorization: %s', $request->headers->get('authorization')));
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $adsRegex = '/ADS account="(?P<account>[^"]+)", nonce="(?P<nonce>[a-zA-Z0-9+\/]+={0,2})", created="(?P<created>[^"]+)", signature="(?P<signature>[^"]+)"/';
        if (
            !$request->headers->has('authorization') || 1 !== preg_match(
                $adsRegex,
                $request->headers->get('authorization'),
                $matches
            )
        ) {
            throw new UnauthorizedHttpException('ADS');
        }

        try {
            $created = new DateTimeImmutable($matches['created']);
        } catch (\Exception $exception) {
            throw new AuthenticationException('Invalid date');
        }

        return $this->authenticate(
            $matches['account'],
            $matches['nonce'],
            $created,
            $matches['signature']
        );
    }

    public function authenticate(
        string $account,
        string $nonce,
        DateTimeInterface $created,
        string $signature
    ): string {
        try {
            $account = AdsUtils::normalizeAddress($account);
        } catch (RuntimeException $exception) {
            throw new AuthenticationException('Invalid account');
        }

        if ($this->validateSignature($account, $nonce, $created, $signature)) {
            $this->logger->debug(sprintf('ADS Authenticator: digest valid for %s', $account));
            return $account;
        }
        throw new AuthenticationException('The ADS authentication failed');
    }

    protected function validateSignature(
        string $account,
        string $nonce,
        DateTimeInterface $created,
        string $signature
    ): bool {
        // Check created time is not in the future
        if ($created->getTimestamp() > time()) {
            throw new AuthenticationException('Date from the future');
        }

        // Expire timestamp after 5 minutes
        if (time() - $created->getTimestamp() > self::LIFETIME) {
            $this->logger->debug(
                sprintf(
                    'ADS Authenticator: Expire timestamp after %d seconds (%s)',
                    self::LIFETIME,
                    $created->format('c')
                )
            );
            throw new AuthenticationException('Expired token');
        }

        // Validate that the nonce is *not* in cache
        // if it is, this could be a replay attack
        $cacheKey = md5($nonce);
        if (Cache::has($cacheKey)) {
            $this->logger->debug(sprintf('ADS Authenticator: Previously used nonce detected (%s)', $nonce));
            throw new AuthenticationException('Previously used nonce detected');
        }

        // Store the item in cache for 5 minutes
        Cache::add($cacheKey, true, self::LIFETIME);

        // Validate signature
        try {
            $publicKey = $this->adsClient->getPublicKeyByAccountAddress($account);
        } catch (AdsException $exception) {
            $this->logger->debug(sprintf('ADS Authenticator: Invalid account (%s)', $account));
            throw new AuthenticationException('Invalid account');
        }

        try {
            return $this->signatureVerifier->verifyNonce($publicKey, $signature, $nonce, $created);
        } catch (SignatureVerifierException $exception) {
            throw new AuthenticationException('Invalid signature');
        }
    }
}

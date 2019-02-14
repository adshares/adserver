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

namespace App\Verifier;

use App\Verifier\Dto\VerifierResponse;
use App\Verifier\Exception\BannerNotVerifiedException;
use function array_key_exists;

class DummyBannerVerifier implements BannerVerifierInterface
{
    /** @var string */
    private $keyword;

    /** @var SignatureVerifierInterface */
    private $signatureVerifier;

    public function __construct(string $keyword, SignatureVerifierInterface $signatureVerifier)
    {
        $this->keyword = $keyword;
        $this->signatureVerifier = $signatureVerifier;
    }

    private $banners = [
        'C1310C68A2104460BCD18DFD960650F1' => self::KEYWORD_ACCEPTED,
        'C1310C68A2104460BCD18DFD960650F2' => self::KEYWORD_ACCEPTED,
        'C1310C68A2104460BCD18DFD960650F3' => self::KEYWORD_DECLINED,
        'C1310C68A2104460BCD18DFD960650F4' => self::KEYWORD_DECLINED,
        'C1310C68A2104460BCD18DFD960650F5' => self::KEYWORD_DECLINED,
        'b6454dbc67a94b108e3895700d570ef0' => self::KEYWORD_ACCEPTED,
        '0741db38a3ab463d956254f31a680a89' => self::KEYWORD_DECLINED,
    ];

    public function verify(string $bannerId, bool $trusted = false): void
    {
        // TODO: Implement verify() method.
    }

    public function fetchVerifiedBanner(string $bannerId): VerifierResponse
    {
        if (!array_key_exists($bannerId, $this->banners)) {
            throw new BannerNotVerifiedException(sprintf('Banner %s does not exist.', $bannerId));
        }

        $keywords = (array)$this->createKeyword($bannerId);
        $signature = $this->signatureVerifier->create($keywords, $bannerId);

        return new VerifierResponse($keywords, $signature);
    }

    private function createSignature(string $bannerId, array $keywords): string
    {
        // @todo sign keywords
        return '';
    }

    private function createKeyword(string $bannerId): string
    {
        return sprintf('%s:%s', $this->keyword, $this->banners[$bannerId]);
    }
}

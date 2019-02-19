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

namespace Adshares\Classify\Infrastructure\Service;

use Adshares\Classify\Application\Service\ClassifierInterface;
use Adshares\Classify\Application\Service\SignatureVerifierInterface;
use Adshares\Classify\Application\Exception\BannerNotVerifiedException;
use Adshares\Classify\Domain\Model\Classification;
use Adshares\Classify\Domain\Model\ClassificationCollection;
use function array_key_exists;

class DummyClassifier implements ClassifierInterface
{
    /** @var string */
    private $keyword;

    /** @var SignatureVerifierInterface */
    private $signatureVerifier;
    private $banners = [
        'C1310C68A2104460BCD18DFD960650F1' => [
            'publisherId' => 'C1310C68A2104460BCD18DFD960650P1',
            'siteId' => null,
            'status' => self::KEYWORD_ACCEPTED,
        ],
        'C1310C68A2104460BCD18DFD960650F2' => [
            'publisherId' => 'C1310C68A2104460BCD18DFD960650P1',
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
        'C1310C68A2104460BCD18DFD960650F3' => [
            'publisherId' => 'C1310C68A2104460BCD18DFD960650P1',
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
        'C1310C68A2104460BCD18DFD960650F4' => [
            'publisherId' => 'C1310C68A2104460BCD18DFD960650P1',
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
        'b6454dbc67a94b108e3895700d570ef0' => [
            'publisherId' => 'C1310C68A2104460BCD18DFD960650P1',
            'siteId' => null,
            'status' => self::KEYWORD_ACCEPTED,
        ],
        '0741db38a3ab463d956254f31a680a89' => [
            'publisherId' => 'C1310C68A2104460BCD18DFD960650P1',
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
    ];

    public function __construct(string $keyword, SignatureVerifierInterface $signatureVerifier)
    {
        $this->keyword = $keyword;
        $this->signatureVerifier = $signatureVerifier;
    }

    public function fetch(string $bannerId): ClassificationCollection
    {
        if (!array_key_exists($bannerId, $this->banners)) {
            throw new BannerNotVerifiedException(sprintf('Banner %s does not exist.', $bannerId));
        }

        $dummy = $this->banners[$bannerId];
        $classification = Classification::createUnsigned(
            $this->keyword,
            $dummy['publisherId'],
            $bannerId,
            $dummy['siteId'],
            $dummy['status']
        );

        $signature = $this->signatureVerifier->create($classification->keyword(), $bannerId);
        $classification->sign($signature);

        return new ClassificationCollection($classification);
    }

    public function classify(string $bannerId, ?string $site): void
    {
        // TODO: Implement classify() method.
    }
}

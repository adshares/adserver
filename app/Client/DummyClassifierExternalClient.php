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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Repository\Common\Dto\ClassifierExternal;
use Adshares\Adserver\Utilities\ClassifierExternalKeywordsSerializer;
use Adshares\Common\Application\Dto\Taxonomy;
use Illuminate\Http\Request;
use SodiumException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function time;

final class DummyClassifierExternalClient implements ClassifierExternalClient
{
    private const PRIVATE_KEY = 'FF767FC8FAF9CFA8D2C3BD193663E8B8CAC85005AD56E085FAB179B52BD88DD6';

    public function requestClassification(ClassifierExternal $classifier, array $data): void
    {
        $callbackUrl = $data['callback_url'];
        $classifierName = substr($callbackUrl, 1 + strrpos($callbackUrl, '/'));

        $requests = $data['banners'];

        $dataOut = [];
        foreach ($requests as $classificationRequest) {
            $checksum = $classificationRequest['checksum'];
            $timestamp = time();
            $keywords = ['category' => ['crypto', 'gambling']];
            $message = hash(
                'sha256',
                hex2bin($checksum) . $timestamp . ClassifierExternalKeywordsSerializer::serialize($keywords)
            );

            try {
                $signature = $this->sign($message);
            } catch (SodiumException $exception) {
                throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Cannot create signature');
            }

            $dataOut[] = [
                'id' => $classificationRequest['id'],
                'keywords' => $keywords,
                'signature' => $signature,
                'timestamp' => $timestamp,
            ];
        }

        $request = Request::create(
            route('demand-classifications-update', ['classifier' => $classifierName], false),
            'PATCH',
            $dataOut
        );
        app()->handle($request);
    }

    public function fetchTaxonomy(ClassifierExternal $classifier): Taxonomy
    {
        return (new DummyAdClassifyClient())->fetchFilteringOptions();
    }

    /**
     * @param string $message
     *
     * @return string
     * @throws SodiumException
     */
    private function sign(string $message): string
    {
        $keyPair = sodium_crypto_sign_seed_keypair(hex2bin(self::PRIVATE_KEY));
        $keySecret = sodium_crypto_sign_secretkey($keyPair);

        return bin2hex(sodium_crypto_sign_detached($message, $keySecret));
    }
}

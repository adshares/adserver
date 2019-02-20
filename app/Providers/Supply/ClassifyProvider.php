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

namespace Adshares\Adserver\Providers\Supply;

use Adshares\Classify\Application\Service\ClassifierInterface;
use Adshares\Classify\Application\Service\SignatureVerifierInterface;
use Adshares\Classify\Infrastructure\Service\DummyClassifier;
use Adshares\Classify\Infrastructure\Service\SodiumCompatSignatureVerifier;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;

class ClassifyProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ClassifierInterface::class,
            function (Application $app) {
                $keyword = (string)config('app.classify_keyword');
                return new DummyClassifier($keyword, $app->make(SignatureVerifierInterface::class));
            }
        );

        $this->app->bind(
            SignatureVerifierInterface::class,
            function () {
                $privateKey = (string)config('app.classify_secret');
                return new SodiumCompatSignatureVerifier($privateKey);
            }
        );
    }
}

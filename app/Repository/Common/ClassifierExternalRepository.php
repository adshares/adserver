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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Repository\Common\Dto\ClassifierExternal;

class ClassifierExternalRepository
{
    public function fetchPublicKeyByClassifierName(string $name): ?string
    {
        if (null !== ($classifier = $this->fetchClassifierByName($name))) {
            return $classifier->getPublicKey();
        }

        return null;
    }

    public function fetchClassifierByName(string $name): ?ClassifierExternal
    {
        if ($this->fetchDefaultClassifierName() === $name) {
            $publicKey = (string)config('app.classifier_external_public_key') ?: null;
            $baseUrl = (string)config('app.classifier_external_base_url') ?: null;
            $apiKeyName = (string)config('app.classifier_external_api_key_name') ?: null;
            $apiKeySecret = (string)config('app.classifier_external_api_key_secret') ?: null;

            if (null !== $publicKey && null !== $baseUrl && null !== $apiKeyName && null !== $apiKeySecret) {
                return new ClassifierExternal($name, $publicKey, $baseUrl, $apiKeyName, $apiKeySecret);
            }
        }

        return null;
    }

    public function fetchClassifiers(): array
    {
        if (null !== ($classifier = $this->fetchDefaultClassifier())) {
            return [$classifier];
        }

        return [];
    }

    public function fetchDefaultClassifier(): ?ClassifierExternal
    {
        if (null !== ($name = $this->fetchDefaultClassifierName())) {
            return $this->fetchClassifierByName($name);
        }

        return null;
    }

    public function fetchDefaultClassifierName(): ?string
    {
        return (string)config('app.classifier_external_name') ?: null;
    }

    public function fetchRequiredClassifiersNames(): array
    {
        $name = $this->fetchDefaultClassifierName();

        return $name ? [$name] : [];
    }
}

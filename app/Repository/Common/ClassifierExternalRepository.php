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
            $url = (string)config('app.classifier_external_url') ?: null;
            $clientName = (string)config('app.classifier_external_client_name') ?: null;
            $clientApiKey = (string)config('app.classifier_external_client_api_key') ?: null;

            if (null !== $publicKey && null !== $url && null !== $clientName && null !== $clientApiKey) {
                return new ClassifierExternal($name, $publicKey, $url, $clientName, $clientApiKey);
            }
        }

        return null;
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
}

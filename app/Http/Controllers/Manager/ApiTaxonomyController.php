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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiTaxonomyController extends Controller
{
    public function __construct(
        private readonly OptionsController $optionsController,
    ) {
    }

    public function media(): JsonResponse
    {
        $response = $this->optionsController->media();
        $this->extractAsData($response);
        return $response;
    }

    public function medium(string $medium, Request $request): JsonResponse
    {
        $response = $this->optionsController->medium($medium, $request);
        $this->extractAsData($response);
        return $response;
    }

    public function vendors(string $medium): JsonResponse
    {
        $response = $this->optionsController->vendors($medium);
        $this->extractAsData($response);
        return $response;
    }

    private function extractAsData(JsonResponse $response): void
    {
        $response->setData(['data' => json_decode($response->getContent())]);
    }
}

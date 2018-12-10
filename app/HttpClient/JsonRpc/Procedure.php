<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\HttpClient\JsonRpc;

use Adshares\Common\Domain\ValueObject\Uuid;
use function GuzzleHttp\json_encode;

final class Procedure
{
    private const RPC_VERSION = '2.0';

    /** @var string */
    private $id;

    /** @var string */
    private $method;

    /** @var array */
    private $params;

    public function __construct(string $method, array $params)
    {
        $this->id = (string)Uuid::v4();
        $this->method = $method;
        $this->params = $params;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function toArray(): array
    {
        return [
            'jsonrpc' => self::RPC_VERSION,
            'id' => $this->id,
            'method' => $this->method,
            'params' => $this->params,
        ];
    }

    public function id(): string
    {
        return $this->id;
    }
}

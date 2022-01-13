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

namespace Adshares\Common\Application\Dto;

use Illuminate\Contracts\Support\Arrayable;

class Gateway implements Arrayable
{
    private string $code;
    private string $name;
    private ?string $description;
    private int $chainId;
    private string $address;
    private string $contractAddress;
    private string $format;
    private string $prefix;

    public function __construct(
        string $code,
        string $name,
        ?string $description,
        int $chainId,
        string $address,
        string $contractAddress,
        string $format,
        string $prefix
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
        $this->chainId = $chainId;
        $this->address = $address;
        $this->contractAddress = $contractAddress;
        $this->format = $format;
        $this->prefix = $prefix;
    }


    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getChainId(): int
    {
        return $this->chainId;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getContractAddress(): string
    {
        return $this->contractAddress;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'chain_id' => $this->chainId,
            'address' => $this->address,
            'contract_address' => $this->contractAddress,
            'format' => $this->format,
            'prefix' => $this->prefix,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['code'],
            $data['name'],
            $data['description'],
            (int)$data['chain_id'],
            $data['address'],
            $data['contract_address'],
            $data['format'],
            $data['prefix'],
        );
    }
}

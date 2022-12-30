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

namespace Adshares\Adserver\Http\Requests\Campaign;

use Adshares\Adserver\Models\Banner;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Support\Str;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;

class BannerValidator
{
    private ?array $supportedScopesByTypes = null;

    public function __construct(private readonly Medium $medium)
    {
    }

    public function validateBanner(array $banner): void
    {
        foreach (
            [
                'type' => Banner::TYPE_MAXIMAL_LENGTH,
                'scope' => Banner::SIZE_MAXIMAL_LENGTH,
                'name' => Banner::NAME_MAXIMAL_LENGTH,
            ] as $field => $maxLength
        ) {
            self::validateField($banner, $field);
            self::validateFieldMaximumLength($banner[$field], $maxLength, $field);
        }
        $type = $banner['type'];
        if (Banner::TEXT_TYPE_DIRECT_LINK !== $type) {
            self::validateField($banner, 'url');
        }

        $size = $banner['scope'];
        $this->validateScope($type, $size);
    }

    public function validateBannerMetaData(array $banner): void
    {
        self::validateField($banner, 'name');
        self::validateFieldMaximumLength($banner['name'], Banner::NAME_MAXIMAL_LENGTH, 'name');

        self::validateField($banner, 'file_id');
        try {
            Uuid::fromString($banner['file_id']);
        } catch (InvalidUuidStringException) {
            throw new InvalidArgumentException('Field `fileId` must be an ID');
        }
    }

    private function initializeSupportedScopesByTypes(): void
    {
        $supported = [];

        foreach ($this->medium->getFormats() as $format) {
            $supported[$format->getType()] = $format->getScopes();
        }

        $this->supportedScopesByTypes = $supported;
    }

    private static function validateField(array $banner, string $field): void
    {
        if (!isset($banner[$field])) {
            throw new InvalidArgumentException(sprintf('Field `%s` is required', Str::camel($field)));
        }
        if (!is_string($banner[$field]) || 0 === strlen($banner[$field])) {
            throw new InvalidArgumentException(sprintf('Field `%s` must be a non-empty string', Str::camel($field)));
        }
    }

    private static function validateFieldMaximumLength(string $value, int $maxLength, string $field): void
    {
        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                sprintf('Field `%s` must have at most %d characters', $field, $maxLength)
            );
        }
    }

    public static function validateName($name): void
    {
        $field = 'name';
        self::validateField([$field => $name], $field);
        self::validateFieldMaximumLength($name, Banner::NAME_MAXIMAL_LENGTH, $field);
    }

    public function validateScope(string $type, string $scope): void
    {
        if (null === $this->supportedScopesByTypes) {
            $this->initializeSupportedScopesByTypes();
        }

        if (!isset($this->supportedScopesByTypes[$type])) {
            throw new InvalidArgumentException(sprintf('Invalid type (%s)', $type));
        }

        if (Banner::TEXT_TYPE_VIDEO === $type) {
            if (1 !== preg_match('/^[0-9]+x[0-9]+$/', $scope)) {
                throw new InvalidArgumentException(sprintf('Invalid scope (%s)', $scope));
            }
            if (
                empty(
                    Size::findMatchingWithSizes(
                        array_keys($this->supportedScopesByTypes[$type]),
                        ...Size::toDimensions($scope)
                    )
                )
            ) {
                throw new InvalidArgumentException(sprintf('Invalid scope (%s). No match', $scope));
            }
            return;
        }

        if (!isset($this->supportedScopesByTypes[$type][$scope])) {
            throw new InvalidArgumentException(sprintf('Invalid scope (%s)', $scope));
        }
    }

    public function validateMimeType(string $bannerType, ?string $mimeType): void
    {
        if (null === $mimeType) {
            throw new InvalidArgumentException('Unknown mime');
        }
        if (!in_array($mimeType, $this->getSupportedMimesForBannerType($bannerType), true)) {
            throw new InvalidArgumentException(
                sprintf('Not supported ad mime type `%s` for %s creative', $mimeType, $bannerType)
            );
        }
    }

    private function getSupportedMimesForBannerType(string $type): array
    {
        foreach ($this->medium->getFormats() as $format) {
            if ($format->getType() === $type) {
                return $format->getMimes();
            }
        }
        throw new InvalidArgumentException(sprintf('Not supported ad type `%s`', $type));
    }
}

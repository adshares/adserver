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
            $this->validateFieldMaximumLength($banner[$field], $maxLength, $field);
        }
        $type = $banner['type'];
        if (Banner::TEXT_TYPE_DIRECT_LINK !== $type) {
            self::validateField($banner, 'url');
        }

        if (null === $this->supportedScopesByTypes) {
            $this->initializeSupportedScopesByTypes();
        }

        if (!isset($this->supportedScopesByTypes[$type])) {
            throw new InvalidArgumentException(sprintf('Invalid banner type (%s)', $type));
        }

        $size = $banner['scope'];
        if (Banner::TEXT_TYPE_VIDEO === $type) {
            if (1 !== preg_match('/^[0-9]+x[0-9]+$/', $size)) {
                throw new InvalidArgumentException(sprintf('Invalid video size (%s)', $size));
            }
            if (
                empty(
                    Size::findMatchingWithSizes(
                        array_keys($this->supportedScopesByTypes[$type]),
                        ...Size::toDimensions($size)
                    )
                )
            ) {
                throw new InvalidArgumentException(sprintf('Invalid video size (%s). No match', $size));
            }
            return;
        }

        if (!isset($this->supportedScopesByTypes[$type][$size])) {
            throw new InvalidArgumentException(sprintf('Invalid banner scope (%s)', $size));
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
            throw new InvalidArgumentException(sprintf('Field `%s` is required', $field));
        }
        if (!is_string($banner[$field]) || 0 === strlen($banner[$field])) {
            throw new InvalidArgumentException(sprintf('Field `%s` must be a non-empty string', $field));
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
}

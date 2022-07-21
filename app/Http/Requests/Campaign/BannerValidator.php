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
    private Medium $medium;
    private ?array $supportedScopesByTypes = null;

    public function __construct(Medium $medium)
    {
        $this->medium = $medium;
    }

    public function validateBanner(array $banner): void
    {
        foreach (['creative_type', 'creative_size', 'name', 'url'] as $field) {
            if (!isset($banner[$field])) {
                throw new InvalidArgumentException(sprintf('Field `%s` is required', $field));
            }
            if (!is_string($banner[$field]) || 0 === strlen($banner[$field])) {
                throw new InvalidArgumentException(sprintf('Field `%s` must be a non-empty string', $field));
            }
        }

        if (null === $this->supportedScopesByTypes) {
            $this->initializeSupportedScopesByTypes();
        }

        $type = $banner['creative_type'];
        if (!isset($this->supportedScopesByTypes[$type])) {
            throw new InvalidArgumentException(sprintf('Invalid banner type (%s)', $type));
        }

        $size = $banner['creative_size'];
        if ($type === Banner::TEXT_TYPE_VIDEO) {
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
            throw new InvalidArgumentException(sprintf('Invalid banner size (%s)', $size));
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
}

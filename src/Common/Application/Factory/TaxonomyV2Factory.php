<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Common\Application\Factory;

use Adshares\Common\Application\Dto\TaxonomyV2;
use Adshares\Common\Exception\InvalidArgumentException;
use GuzzleHttp\Utils;

class TaxonomyV2Factory
{
    private const JSON_PATH_MATCH_REGEXP = '(\[\?\(@\.[^=]+=[^]]+\)]|\.[^.\[]+|\[]$)';
    private const KEY_ADD_VALUE = 'add_value';
    private const KEY_PATH_FRAGMENTS = 'path_fragments';

    public static function fromJson(string $json): TaxonomyV2
    {
        $data = Utils::jsonDecode($json, true);
        $data = self::replaceParameters($data);
        $data = self::appendVendors($data);

        return TaxonomyV2::fromArray($data);
    }

    private static function replaceParameters($data): array
    {
        if (array_key_exists('parameters', $data) && is_array($data['parameters'])) {
            $parameters = $data['parameters'];
            unset($data['parameters']);

            array_walk_recursive(
                $data,
                function (&$value) use ($parameters) {
                    if (is_string($value) && $value[0] === '@' && array_key_exists($value, $parameters)) {
                        $value = $parameters[$value];
                    }
                },
                $parameters
            );
        }
        return $data;
    }

    private static function appendVendors(array $data): array
    {
        if (array_key_exists('vendors', $data) && is_array($data['vendors'])) {
            self::validateVendors($data['vendors']);
            $vendorsToAdd = [];

            foreach ($data['vendors'] as $vendorData) {
                $baseData = self::getBaseData($data, $vendorData['medium']);
                $baseData['vendor'] = $vendorData['name'];
                $baseData['vendorLabel'] = $vendorData['label'];

                $keys = [
                    'formats',
                    'targeting',
                ];
                foreach ($keys as $key) {
                    foreach ($vendorData[$key] ?? [] as $change) {
                        $baseData = self::applyChange(
                            $baseData,
                            $key,
                            self::parseJsonPath($change['path']),
                            $change['value'],
                        );
                    }
                }
                $vendorsToAdd[] = $baseData;
            }

            if (!empty($vendorsToAdd)) {
                $data['media'] = array_merge($data['media'], $vendorsToAdd);
            }
        }

        return $data;
    }

    private static function validateVendors(array $vendors): void
    {
        foreach ($vendors as $vendorData) {
            $fields = [
                'medium',
                'name',
                'label',
            ];

            foreach ($fields as $field) {
                if (!array_key_exists($field, $vendorData)) {
                    throw new InvalidArgumentException(
                        sprintf('The field `vendors.*.%s` is required.', $field)
                    );
                }
                if (!is_string($vendorData[$field])) {
                    throw new InvalidArgumentException(
                        sprintf('The field `vendors.*.%s` must be a string.', $field)
                    );
                }
            }

            self::validateChanges($vendorData);
        }
    }

    private static function validateChanges($vendorData): void
    {
        $keys = [
            'formats',
            'targeting',
        ];
        foreach ($keys as $key) {
            if (array_key_exists($key, $vendorData)) {
                if (!is_array($vendorData[$key])) {
                    throw new InvalidArgumentException(
                        sprintf('The field `vendors.*.%s` must be an array.', $key)
                    );
                }
                foreach ($vendorData[$key] as $change) {
                    if (!is_array($change)) {
                        throw new InvalidArgumentException(
                            sprintf('The field `vendors.*.%s.*` must be an array.', $key)
                        );
                    }
                    self::validateChange($change, $key);
                }
            }
        }
    }

    private static function validateChange(array $change, string $key): void
    {
        if (!array_key_exists('path', $change)) {
            throw new InvalidArgumentException(
                sprintf('The field `vendors.*.%s.*.path` is required.', $key)
            );
        }
        if (!is_string($change['path'])) {
            throw new InvalidArgumentException(
                sprintf('The key in `vendors.*.%s.*.path` must be a string.', $key)
            );
        }
        $supportedJsonPath = '/^\$' . self::JSON_PATH_MATCH_REGEXP . '+$/';
        if (1 !== preg_match($supportedJsonPath, $change['path'])) {
            throw new InvalidArgumentException(
                $change['path'] .
                sprintf('The key in `vendors.*.%s.*.path` must be a supported JSON path.', $key)
            );
        }
        if (!array_key_exists('value', $change)) {
            throw new InvalidArgumentException(
                sprintf('The field `vendors.*.%s.*.value` is required.', $key)
            );
        }
        if ($change['value'] !== null && !is_array($change['value'])) {
            throw new InvalidArgumentException(
                sprintf('The value in `vendors.*.%s.*.value` must be an array or null.', $key)
            );
        }
    }

    private static function getBaseData(array $data, string $medium): ?array
    {
        foreach ($data['media'] ?? [] as $mediumData) {
            if ($medium === $mediumData['name'] ?? '') {
                return $mediumData;
            }
        }
        throw new InvalidArgumentException('The field `medium` must match existing medium.');
    }

    private static function parseJsonPath(string $path): array
    {
        $matches = [];
        $result = preg_match_all('/' . self::JSON_PATH_MATCH_REGEXP . '/', $path, $matches);
        if (false === $result || $result < 1) {
            throw new InvalidArgumentException(sprintf('Path `%s` is invalid.', $path));
        }
        $addValue = false;
        $pathFragments = $matches[1];
        if ($pathFragments[count($pathFragments) - 1] === '[]') {
            $addValue = true;
            array_pop($pathFragments);
        }
        $pathFragments = array_map(
            fn($fragment) => $fragment[0] === '.' ? substr($fragment, 1) : $fragment,
            $pathFragments
        );

        return [
            self::KEY_ADD_VALUE => $addValue,
            self::KEY_PATH_FRAGMENTS => $pathFragments,
        ];
    }

    private static function applyChange(
        array $baseData,
        string $key,
        array $pathParsingResult,
        ?array $value
    ): array {
        $temp = &$baseData[$key];
        foreach ($pathParsingResult[self::KEY_PATH_FRAGMENTS] as $pathFragment) {
            if (str_starts_with($pathFragment, '[?(@.') && str_ends_with($pathFragment, ')]')) {
                [$k, $v] = explode('=', substr($pathFragment, strlen('[?(@.'), -strlen(')]')), 2);
                for ($i = 0; $i < count($temp); $i++) {
                    if (($temp[$i][$k] ?? '') === $v) {
                        $pathFragment = $i;
                        break;
                    }
                }
            }
            if (!array_key_exists($pathFragment, $temp)) {
                throw new InvalidArgumentException(sprintf('Path fragment `%s` is invalid.', $pathFragment));
            }
            $temp = &$temp[$pathFragment];
        }
        if ($pathParsingResult[self::KEY_ADD_VALUE]) {
            $temp[] = $value;
        } else {
            $temp = $value;
        }
        unset($temp);

        return $baseData;
    }
}

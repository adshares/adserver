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

namespace Adshares\Adserver\Tests\Client\Mapper;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Tests\TestCase;
use function json_decode;

class AbstractFilterMapperTest extends TestCase
{
    public function testGenerateNestedStructure(): void
    {
        $keywordsJson = <<<JSON
{"device":{"type":"desktop","os":"unix","browser":"chrome"},"user":{"language":["pl","en"],"country":"pl"},"site":{"url":["\/\/demo-site.adshares.net","net","adshares.net","demo-site.adshares.net"],"tag":["pets: cats","info"]}} 
JSON;
        $keywords = json_decode($keywordsJson, true);

        self::assertSame(
            [
                'device:type' => ['desktop'],
                'device:os' => ['unix'],
                'device:browser' => ['chrome'],
                'user:language' => ['en', 'pl'],
                'site:url' => [
                    '//demo-site.adshares.net',
                    'net',
                    'adshares.net',
                    'demo-site.adshares.net',
                ],
                'site:tag' => ['pets: cats'  , 'info'],

            ],
            AbstractFilterMapper::generateNestedStructure($keywords)
        );
    }
}


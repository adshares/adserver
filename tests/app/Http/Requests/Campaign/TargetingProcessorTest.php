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

namespace Adshares\Adserver\Tests\Http\Requests\Campaign;

use Adshares\Adserver\Http\Requests\Campaign\TargetingProcessor;
use Adshares\Adserver\Tests\TestCase;

final class TargetingProcessorTest extends TestCase
{
    public function testWhileNoAvailableOptions(): void
    {
        $targetingProcessor = new TargetingProcessor([]);

        $result = $targetingProcessor->processTargeting($this->getTargetingValid());

        $this->assertEquals([], $result);
    }

    public function testWhileTargetingEmpty(): void
    {
        $targetingProcessor = new TargetingProcessor($this->getTargetingSchema());

        $result = $targetingProcessor->processTargeting([]);

        $this->assertEquals([], $result);
    }

    public function testWhileTargetingValid(): void
    {
        $targetingProcessor = new TargetingProcessor($this->getTargetingSchema());

        $targetingValid = $this->getTargetingValid();
        $result = $targetingProcessor->processTargeting($targetingValid);

        $this->assertEquals($targetingValid, $result);
    }

    /**
     * @dataProvider invalidTargetingProvider
     *
     * @param string $testVariant
     * @param array $invalidTargeting
     */
    public function testWhileTargetingInvalid(string $testVariant, array $invalidTargeting): void
    {
        $targetingProcessor = new TargetingProcessor($this->getTargetingSchema());

        $result = $targetingProcessor->processTargeting($invalidTargeting);

        $this->assertEquals($this->getTargetingValid(), $result);
    }

    private function getTargetingValid(): array
    {
        return json_decode(
            <<<JSON
{
    "user": {
        "country": [
            "af",
            "gw"
        ],
        "language": [
            "ak"
        ]
    },
    "site": {
        "domain": [
            "www.a.pl"
        ]
    },
    "device": {
        "os": [
            "windows"
        ],
        "browser": [
            "opera"
        ]
    }
}
JSON
            ,
            true
        );
    }

    private function getTargetingInvalidUnknownCategory(): array
    {
        $targeting = $this->getTargetingValid();

        $targeting['global'] = [
            'parameter' => ['value'],
        ];

        return $targeting;
    }

    private function getTargetingInvalidUnknownGroup(): array
    {
        $targeting = $this->getTargetingValid();

        $targeting['user']['job'] = ['tester'];

        return $targeting;
    }

    private function getTargetingInvalidUnknownValue(): array
    {
        $targeting = $this->getTargetingValid();

        array_push($targeting['user']['country'], 'xx');

        return $targeting;
    }

    public function invalidTargetingProvider(): array
    {
        return [
            ['unknown category', $this->getTargetingInvalidUnknownCategory()],
            ['unknown group', $this->getTargetingInvalidUnknownGroup()],
            ['unknown value', $this->getTargetingInvalidUnknownValue()],
        ];
    }

    private function getTargetingSchema(): array
    {
        $targetingSchema = file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'targeting_schema.json');

        return json_decode($targetingSchema, true);
    }
}

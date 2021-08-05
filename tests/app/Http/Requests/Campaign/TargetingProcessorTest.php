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

namespace Adshares\Adserver\Tests\Http\Requests\Campaign;

use Adshares\Adserver\Http\Requests\Campaign\TargetingProcessor;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Mock\Client\DummyAdUserClient;

final class TargetingProcessorTest extends TestCase
{
    public function testWhileNoAvailableOptions(): void
    {
        $targetingProcessor = new TargetingProcessor(new Selector());

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
     * @param array $invalidTargeting
     */
    public function testWhileTargetingInvalid(array $invalidTargeting): void
    {
        $targetingProcessor = new TargetingProcessor($this->getTargetingSchema());

        $result = $targetingProcessor->processTargeting($invalidTargeting);

        $this->assertEquals($this->getTargetingValid(), $result);
    }

    public function testWhileTargetingInvalidNotInArray(): void
    {
        $targetingProcessor = new TargetingProcessor($this->getTargetingSchema());

        $result = $targetingProcessor->processTargeting($this->getTargetingInvalidValueNotInArray());

        $this->assertEquals([], $result);
    }

    private function getTargetingValid(): array
    {
        return [
            'user' => [
                'country' => [
                    'af',
                    'gw',
                ],
                'language' => [
                    'ak',
                ],
            ],
            'site' => [
                'domain' => [
                    'www.a.pl',
                ],
            ],
            'device' => [
                'os' => [
                    'windows',
                ],
                'browser' => [
                    'opera',
                ],
            ],
        ];
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

    private function getTargetingInvalidRepeatedValues(): array
    {
        $targeting = $this->getTargetingValid();

        array_push($targeting['user']['country'], 'af');

        return $targeting;
    }

    private function getTargetingInvalidValueNotInArray(): array
    {
        return [
            'site' => [
                'domain' => 'www.a.pl',
            ],
        ];
    }

    public function invalidTargetingProvider(): array
    {
        return [
            'unknown category' => [$this->getTargetingInvalidUnknownCategory()],
            'unknown group' => [$this->getTargetingInvalidUnknownGroup()],
            'unknown value' => [$this->getTargetingInvalidUnknownValue()],
            'repeated values' => [$this->getTargetingInvalidRepeatedValues()],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(
            AdUser::class,
            static function () {
                return new DummyAdUserClient();
            }
        );
    }

    private function getTargetingSchema(): Selector
    {
        return $this->app->make(ConfigurationRepository::class)->fetchTargetingOptions();
    }
}

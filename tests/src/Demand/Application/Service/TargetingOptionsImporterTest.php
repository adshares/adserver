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

namespace Adshares\Tests\Demand\Application\Service;

use Adshares\Adserver\Services\Supply\DefaultBannerPlaceholderGenerator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Demand\Application\Service\TargetingOptionsImporter;
use Adshares\Mock\Client\DummyAdUserClient;

final class TargetingOptionsImporterTest extends TestCase
{
    public function testImport(): void
    {
        $configurationRepository = self::createMock(ConfigurationRepository::class);
        $configurationRepository->expects(self::once())->method('storeTaxonomyV2');
        $generator = self::createMock(DefaultBannerPlaceholderGenerator::class);
        $generator->expects(self::once())->method('generate')->with(false);

        $targetingOptionsImporter = new TargetingOptionsImporter(
            new DummyAdUserClient(),
            $configurationRepository,
            $generator,
        );

        $targetingOptionsImporter->import();
    }
}

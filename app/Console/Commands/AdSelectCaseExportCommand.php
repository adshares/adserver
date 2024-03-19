<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Services\Supply\AdSelectCaseExporter;
use Adshares\Adserver\Services\Supply\NetworkImpressionUpdater;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;

class AdSelectCaseExportCommand extends BaseCommand
{
    protected $signature = 'ops:adselect:case:export';

    protected $description = 'Export cases and matching click events to AdSelect';

    public function __construct(
        Locker $locker,
        private readonly AdSelectCaseExporter $adSelectCaseExporter,
        private readonly NetworkImpressionUpdater $networkImpressionUpdater,
    ) {
        parent::__construct($locker);
    }

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info('[AdSelectCaseExport] Command ' . $this->signature . ' already running.');
            return self::FAILURE;
        }

        $this->info('[AdSelectCaseExport] Start command ' . $this->signature);

        //
        //$this->updateImpressions();
        $this->exportCases();
        $this->exportCaseClicks();

        $this->info('[AdSelectCaseExport] Finished exporting to AdSelect.');
        return self::SUCCESS;
    }

    private function updateImpressions(): void
    {
        $this->info('[AdSelectCaseExport] Updating impressions');
        $updatedCount = $this->networkImpressionUpdater->updateWithAdUserData();
        $this->info(sprintf('[AdSelectCaseExport] Updated %s impressions', $updatedCount));
    }

    private function exportCases(): void
    {
        $this->info('[AdSelectCaseExport] Exporting cases');

        try {
            $caseIdFrom = $this->adSelectCaseExporter->getCaseIdToExport();
            $exportedCount = $this->adSelectCaseExporter->exportCases($caseIdFrom);

            $this->info(sprintf('[AdSelectCaseExport] Exported %s cases', $exportedCount));
        } catch (UnexpectedClientResponseException | RuntimeException $exception) {
            $this->error($exception->getMessage());
        }
    }

    private function exportCaseClicks(): void
    {
        $this->info('[AdSelectCaseExport] Exporting clicks');

        try {
            $caseClickIdFrom = $this->adSelectCaseExporter->getCaseClickIdToExport();
            $exportedCount = $this->adSelectCaseExporter->exportCaseClicks($caseClickIdFrom);

            $this->info(sprintf('[AdSelectCaseExport] Exported %s click events', $exportedCount));
        } catch (UnexpectedClientResponseException | RuntimeException $exception) {
            $this->error($exception->getMessage());
        }
    }
}

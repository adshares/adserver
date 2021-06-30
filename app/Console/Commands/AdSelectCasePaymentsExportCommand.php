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

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Services\Supply\AdSelectCaseExporter;
use Adshares\Adserver\Services\Supply\NetworkImpressionUpdater;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;

use function sprintf;

class AdSelectCasePaymentsExportCommand extends BaseCommand
{
    protected $signature = 'ops:adselect:case-payments:export';

    protected $description = 'Export payments to AdSelect';

    /** @var AdSelectCaseExporter */
    private $adSelectCaseExporter;

    /** @var NetworkImpressionUpdater */
    private $networkImpressionUpdater;

    public function __construct(
        Locker $locker,
        AdSelectCaseExporter $adSelectCaseExporter,
        NetworkImpressionUpdater $networkImpressionUpdater
    ) {
        $this->adSelectCaseExporter = $adSelectCaseExporter;
        $this->networkImpressionUpdater = $networkImpressionUpdater;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('[AdSelectCaseExport] Command ' . $this->signature . ' already running.');

            return;
        }

        $this->info('[AdSelectCaseExport] Start command ' . $this->signature);

        try {
            $casePaymentIdFrom = $this->adSelectCaseExporter->getCasePaymentIdToExport();
            $exportedCount = $this->adSelectCaseExporter->exportCasePayments($casePaymentIdFrom);

            $this->info(sprintf('[AdSelectCaseExport] Exported %s payments', $exportedCount));
        } catch (UnexpectedClientResponseException | RuntimeException $exception) {
            $this->error($exception->getMessage());
        }

        $this->info('[AdSelectCaseExport] Finished exporting to AdSelect.');
    }
}

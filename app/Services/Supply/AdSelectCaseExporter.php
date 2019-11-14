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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseClick;
use Adshares\Adserver\Models\NetworkCasePayment;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\AdSelect;
use DateTime;
use function count;

class AdSelectCaseExporter
{
    private const PACKAGE_SIZE = 2000;

    private $adSelectClient;

    public function __construct(AdSelect $adSelectClient)
    {
        $this->adSelectClient = $adSelectClient;
    }

    public function exportCases(int $caseIdFrom): int
    {
        $exported = 0;
        $impressionIdMax = NetworkImpressionUpdater::getLastUpdatedId();

        $maxId = NetworkCase::max('id');
        $totalEstimate = $maxId - $caseIdFrom;

        do {
            $cases = NetworkCase::fetchCasesToExport($caseIdFrom, $impressionIdMax, self::PACKAGE_SIZE, 0);
            $this->adSelectClient->exportCases($cases);

            $exported += count($cases);
            echo "exported: $exported; progress=", $cases->last() ? round(100-($maxId-$cases->last()->id)/$totalEstimate*100,0) : '100', "%\n";
            if(count($cases) > 0) {
                $caseIdFrom = $cases->last()->id+1;
            }
        } while (count($cases) === self::PACKAGE_SIZE);

        return $exported;
    }

    public function exportCaseClicks(int $caseClickIdFrom): int
    {
        $exported = 0;
        $caseIdMax = $this->getCaseIdToExport();

//        echo "caseClickIdFrom=$caseClickIdFrom, caseIdMax=$caseIdMax\n";

        $maxId = NetworkCaseClick::max('id');
        $totalEstimate = $maxId - $caseClickIdFrom;

        do {
            $caseClicks =
                NetworkCaseClick::fetchClicksToExport($caseClickIdFrom, $caseIdMax, self::PACKAGE_SIZE, 0);
            $this->adSelectClient->exportCaseClicks($caseClicks);

            $exported += count($caseClicks);
            echo "exported: $exported; progress=", $caseClicks->last() ? round(100-($maxId-$caseClicks->last()->id)/$totalEstimate*100,0) : '100', "%\n";
            if(count($caseClicks) > 0) {
                $caseClickIdFrom = $caseClicks->last()->id+1;
            }
        } while (count($caseClicks) === self::PACKAGE_SIZE);

        return $exported;
    }

    public function exportCasePayments(int $casePaymentIdFrom): int
    {
        $exported = 0;
        $caseIdMax = $this->getCaseIdToExport();

        $maxId = NetworkCasePayment::max('id');
        $totalEstimate = $maxId - $casePaymentIdFrom;

        do {
            $casePayments =
                NetworkCasePayment::fetchPaymentsToExport($casePaymentIdFrom, $caseIdMax, self::PACKAGE_SIZE, 0);
            $this->adSelectClient->exportCasePayments($casePayments);

            $exported += count($casePayments);
            echo "exported: $exported; progress=", $casePayments->last() ? round(100-($maxId-$casePayments->last()->id)/$totalEstimate*100,0) : '100', "%\n";
            if(count($casePayments) > 0) {
                $casePaymentIdFrom = $casePayments->last()->id+1;
            }
        } while (count($casePayments) === self::PACKAGE_SIZE);

        return $exported;
    }

    public function getCaseIdToExport(): int
    {
        $caseId = $this->adSelectClient->getLastExportedCaseId();

        if ($caseId > 0) {
            return $caseId + 1;
        }

        $periodStart = $this->getExportedPeriodStart();
        $case = NetworkCase::where('created_at', '>=', $periodStart)->first();

        if (null === $case) {
            throw new RuntimeException(sprintf('No case since %s', $periodStart->format(DateTime::ATOM)));
        }

        return $case->id;
    }

    public function getCaseClickIdToExport(): int
    {
        $caseClickId = $this->adSelectClient->getLastExportedCaseClickId();

        if ($caseClickId > 0) {
            return $caseClickId + 1;
        }

        $periodStart = $this->getExportedPeriodStart();
        $caseClick = NetworkCaseClick::where('created_at', '>=', $periodStart)->first();

        if (null === $caseClick) {
            throw new RuntimeException(sprintf('No click since %s', $periodStart->format(DateTime::ATOM)));
        }

        return $caseClick->id;
    }

    public function getCasePaymentIdToExport(): int
    {
        $paymentId = $this->adSelectClient->getLastExportedCasePaymentId();

        if ($paymentId > 0) {
            return $paymentId + 1;
        }

        $periodStart = $this->getExportedPeriodStart();
        $payment = NetworkCasePayment::where('pay_time', '>=', $periodStart)->first();

        if (null === $payment) {
            throw new RuntimeException(sprintf('No payment since %s', $periodStart->format(DateTime::ATOM)));
        }

        return $payment->id;
    }

    private function getExportedPeriodStart(): DateTime
    {
        return new DateTime('-2 weeks');
    }
}

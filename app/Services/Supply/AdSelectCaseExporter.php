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
    private const PACKAGE_SIZE = 500;

    private $adSelectClient;

    public function __construct(AdSelect $adSelectClient)
    {
        $this->adSelectClient = $adSelectClient;
    }

    public function exportCases(int $caseIdFrom): int
    {
        $exported = 0;
        $offset = 0;
        $impressionIdMax = NetworkImpressionUpdater::getLastUpdatedId();

        do {
            $cases = NetworkCase::fetchCasesToExport($caseIdFrom, $impressionIdMax, self::PACKAGE_SIZE, $offset);
            $this->adSelectClient->exportCases($cases);

            $exported += count($cases);
            $offset += self::PACKAGE_SIZE;
        } while (count($cases) === self::PACKAGE_SIZE);

        if (null !== ($id = $cases->max('id'))) {
            self::setLastExportedCaseId($id);
        }

        return $exported;
    }

    public function exportCaseClicks(int $caseClickIdFrom): int
    {
        $exported = 0;
        $offset = 0;
        $caseIdMax = self::getLastExportedCaseId();

        do {
            $caseClicks =
                NetworkCaseClick::fetchClicksToExport($caseClickIdFrom, $caseIdMax, self::PACKAGE_SIZE, $offset);
            $this->adSelectClient->exportCaseClicks($caseClicks);

            $exported += count($caseClicks);
            $offset += self::PACKAGE_SIZE;
        } while (count($caseClicks) === self::PACKAGE_SIZE);

        return $exported;
    }

    public function exportCasePayments(int $casePaymentIdFrom): int
    {
        $exported = 0;
        $offset = 0;
        $caseIdMax = self::getLastExportedCaseId();

        do {
            $casePayments =
                NetworkCasePayment::fetchPaymentsToExport($casePaymentIdFrom, $caseIdMax, self::PACKAGE_SIZE, $offset);
            $this->adSelectClient->exportCasePayments($casePayments);

            $exported += count($casePayments);
            $offset += self::PACKAGE_SIZE;
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

    private static function setLastExportedCaseId(int $id): void
    {
        Config::upsertInt(Config::ADSELECT_LAST_EXPORTED_CASE_ID, $id);
    }

    private static function getLastExportedCaseId(): int
    {
        return Config::fetchInt(Config::ADSELECT_LAST_EXPORTED_CASE_ID);
    }
}

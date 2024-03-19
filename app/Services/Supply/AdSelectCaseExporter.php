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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseClick;
use Adshares\Adserver\Models\NetworkCasePayment;
use Adshares\Adserver\Models\NetworkBoostPayment;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\AdSelect;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Support\Collection;

use function count;

class AdSelectCaseExporter
{
    private const PACKAGE_SIZE = 2000;

    public function __construct(private readonly AdSelect $adSelectClient, private readonly ConsoleOutput $output)
    {
    }

    /**
     * Write a string as information output.
     *
     * @param  string $string
     *
     * @return void
     */
    public function progress($string)
    {
        $this->output->write("<info>$string</info>\r");
    }

    public function exportCases(int $caseIdFrom): int
    {
        $exported = 0;

        $maxId = NetworkCase::max('id');
        $impressionIdMax = 0xFFFFFFFFFFFFF;
        $totalEstimate = $maxId - $caseIdFrom + 1;

        do {
            $caseCandidates = NetworkCase::fetchCasesToExport($caseIdFrom, self::PACKAGE_SIZE);
            $cases = new Collection();
            foreach ($caseCandidates as $case) {
                if ($case->network_impression_id < $impressionIdMax) {
                    $cases->push($case);
                } else {
                    break;
                }
            }

            $this->adSelectClient->exportCases($cases);
            $exported += count($cases);

            $this->progress(
                sprintf(
                    "[AdSelectCaseExport] exported: %7d; progress=%5.1f%%",
                    $exported,
                    $cases->last() && $totalEstimate > 0
                        ? 100 - ($maxId - $cases->last()->id + 1) / $totalEstimate * 100 : '100'
                )
            );

            if (count($cases) > 0) {
                $caseIdFrom = $cases->last()->id + 1;
            }
        } while (count($cases) === self::PACKAGE_SIZE);

        $this->output->writeln("");
        return $exported;
    }

    public function exportCaseClicks(int $caseClickIdFrom): int
    {
        $exported = 0;
        $caseIdMax = $this->getCaseIdToExport();

        $maxId = NetworkCaseClick::max('id');
        $totalEstimate = $maxId - $caseClickIdFrom + 1;

        do {
            $caseClicks = NetworkCaseClick::fetchClicksToExport($caseClickIdFrom, $caseIdMax, self::PACKAGE_SIZE, 0);
            $this->adSelectClient->exportCaseClicks($caseClicks);
            $exported += count($caseClicks);

            $this->progress(
                sprintf(
                    "[AdSelectCaseExport] exported: %7d; progress=%5.1f%%",
                    $exported,
                    $caseClicks->last() && $totalEstimate > 0
                        ? 100 - ($maxId - $caseClicks->last()->id + 1) / $totalEstimate * 100 : '100'
                )
            );

            if (count($caseClicks) > 0) {
                $caseClickIdFrom = $caseClicks->last()->id + 1;
            }
        } while (count($caseClicks) === self::PACKAGE_SIZE);

        $this->output->writeln("");
        return $exported;
    }

    public function exportCasePayments(): int
    {
        $exported = 0;
        $caseIdMax = $this->getCaseIdToExport();
        $casePaymentIdFrom = $this->getCasePaymentIdToExport();

        $maxId = NetworkCasePayment::max('id');
        $totalEstimate = $maxId - $casePaymentIdFrom + 1;

        do {
            $casePayments =
                NetworkCasePayment::fetchPaymentsToExport($casePaymentIdFrom, $caseIdMax, self::PACKAGE_SIZE, 0);
            $this->adSelectClient->exportCasePayments($casePayments);
            $exported += count($casePayments);

            $this->progress(
                sprintf(
                    "[AdSelectCaseExport] exported: %7d; progress=%5.1f%%",
                    $exported,
                    $casePayments->last() && $totalEstimate > 0
                        ? 100 - ($maxId - $casePayments->last()->id + 1) / $totalEstimate * 100 : '100'
                )
            );

            if (count($casePayments) > 0) {
                $casePaymentIdFrom = $casePayments->last()->id + 1;
            }
        } while (count($casePayments) === self::PACKAGE_SIZE);

        $this->output->writeln("");
        return $exported;
    }

    public function exportBoostPayments(): int
    {
        $exportedCount = 0;
        $boostPaymentIdFrom = $this->getBoostPaymentIdToExport();

        do {
            $boostPayments = NetworkBoostPayment::fetchPaymentsToExport($boostPaymentIdFrom, self::PACKAGE_SIZE);
            if ($boostPayments->isEmpty()) {
                break;
            }

            $this->adSelectClient->exportBoostPayments($boostPayments);

            $boostPaymentIdFrom = $boostPayments->last()->id + 1;
            $exportedCount += $boostPayments->count();
        } while ($boostPayments->count() === self::PACKAGE_SIZE);

        return $exportedCount;
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
            throw new RuntimeException(sprintf('No case since %s', $periodStart->format(DateTimeInterface::ATOM)));
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
            throw new RuntimeException(sprintf('No click since %s', $periodStart->format(DateTimeInterface::ATOM)));
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
            throw new RuntimeException(sprintf('No payment since %s', $periodStart->format(DateTimeInterface::ATOM)));
        }

        return $payment->id;
    }

    public function getBoostPaymentIdToExport(): int
    {
        $paymentId = $this->adSelectClient->getLastExportedBoostPaymentId();
        if ($paymentId > 0) {
            return $paymentId + 1;
        }

        $periodStart = $this->getExportedPeriodStart();
        if (null === $payment = NetworkBoostPayment::fetchOldest($periodStart)) {
            throw new RuntimeException(sprintf('No payment since %s', $periodStart->format(DateTimeInterface::ATOM)));
        }

        return $payment->id;
    }

    private function getExportedPeriodStart(): DateTimeImmutable
    {
        return new DateTimeImmutable('-2 days');
    }
}

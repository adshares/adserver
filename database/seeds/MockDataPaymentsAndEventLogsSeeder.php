<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Common\Domain\ValueObject\Uuid;
use Illuminate\Database\Seeder;

class MockDataPaymentsAndEventLogsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('[mock] seeding: payments');
        $payment1 = $this->createPayment('0001-00000005-0001', '0001:00000003:0001', 2);
        $payment2 = $this->createPayment('0001-00000005-0001', '0001:00000003:0002', 3);
        $payment3 = $this->createPayment('0001-00000005-0001', '0001:00000003:0003', 1);
        $payment4 = $this->createPayment('0001-00000005-0002', '0002:00000003:0002', 5);

        $this->command->info('[mock] seeding: event_logs');

        $caseId1 = Uuid::v4();
        $caseId2 = Uuid::v4();
        $caseId3 = Uuid::v4();
        $caseId4 = Uuid::v4();

        $this->createEvent($caseId1, '0001-00000001-0001', 10, $payment1, 'view');
        $this->createEvent($caseId1, '0001-00000001-0001', 100, $payment1, 'click');
        $this->createEvent($caseId2, '0001-00000001-0001', 1, $payment2, 'view');
        $this->createEvent($caseId3, '0001-00000001-0001', 1, $payment3, 'view');
        $this->createEvent($caseId4, '0001-00000002-0001', 2, $payment4, 'view');
        $this->createEvent($caseId4, '0001-00000002-0001', 3, $payment4, 'click');

        factory(EventLog::class)->times(100)->create();
    }

    private function createPayment(string $accountAddress, string $transactionId, int $fee): int
    {
        $payment = new Payment();
        $payment->account_address = $accountAddress;
        $payment->tx_id = $transactionId;
        $payment->tx_time = time() - 3600;
        $payment->fee = $fee;
        $payment->completed = 1;

        $payment->save();

        return $payment->id;
    }

    private function createEvent(Uuid $caseId, string $payTo, int $value, int $paymentId, string $type): void
    {
        $event = new EventLog();
        $event->case_id = (string)$caseId;
        $event->event_id = (string)Uuid::v4();
        $event->user_id = (string)Uuid::v4();
        $event->tracking_id = (string)Uuid::v4();
        $event->banner_id = (string)Uuid::v4();
        $event->zone_id = (string)Uuid::v4();
        $event->event_type = $type;
        $event->pay_to = $payTo;
        $event->event_value_currency = $value;
        $event->paid_amount = $value;
        $event->payment_id = $paymentId;
        $event->publisher_id = (string)Uuid::v4();

        $event->save();
    }
}

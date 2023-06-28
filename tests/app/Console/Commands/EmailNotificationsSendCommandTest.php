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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Mail\Notifications\CampaignDraft;
use Adshares\Adserver\Mail\Notifications\CampaignEnded;
use Adshares\Adserver\Mail\Notifications\CampaignEndedExtend;
use Adshares\Adserver\Mail\Notifications\CampaignEnds;
use Adshares\Adserver\Mail\Notifications\FundsEnds;
use Adshares\Adserver\Mail\Notifications\InactiveAdvertiser;
use Adshares\Adserver\Mail\Notifications\InactivePublisher;
use Adshares\Adserver\Mail\Notifications\InactiveUser;
use Adshares\Adserver\Mail\Notifications\InactiveUserExtend;
use Adshares\Adserver\Mail\Notifications\InactiveUserWhoDeposit;
use Adshares\Adserver\Mail\Notifications\SiteDraft;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Adserver\ViewModel\MediumName;
use DateTimeImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class EmailNotificationsSendCommandTest extends ConsoleTestCase
{
    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan('ops:email-notifications:send')
            ->expectsOutput('Command ops:email-notifications:send already running')
            ->assertExitCode(1);

        Mail::assertNothingQueued();
    }

    public function testHandleCampaignDraft(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        Campaign::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-4 day'),
                'status' => Campaign::STATUS_DRAFT,
                'time_start' => (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
                'updated_at' => new DateTimeImmutable('-4 day'),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(CampaignDraft::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleCampaignEnds(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        UserLedgerEntry::factory()->create(
            [
                'amount' => 50_000 * 1e11,
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'user_id' => $user,
            ]
        );
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
                'time_end' => (new DateTimeImmutable('+2 days 10 minutes'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(CampaignEnds::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleCampaignEnded(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-5 days'))->format(DATE_ATOM),
                'time_end' => (new DateTimeImmutable('-20 hours'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(CampaignEnded::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleLastCampaignEndedLongAgo(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'time_end' => (new DateTimeImmutable('-20 days'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(CampaignEndedExtend::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleCampaignEndedLongAgoButAnotherIsActive(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        UserLedgerEntry::factory()->create(
            [
                'amount' => 100_000 * 1e11,
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'user_id' => $user,
            ]
        );
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'time_end' => (new DateTimeImmutable('-20 days'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'time_end' => (new DateTimeImmutable('+1 week'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    /**
     * @dataProvider mediumNameProvider
     */
    public function testHandleSiteDraft(string $mediumName): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        Site::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-4 day'),
                'medium' => $mediumName,
                'status' => Site::STATUS_DRAFT,
                'updated_at' => new DateTimeImmutable('-4 day'),
                'user_id' => $user,
            ]
        );
        Site::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-4 day'),
                'medium' => $mediumName,
                'status' => Site::STATUS_DRAFT,
                'updated_at' => new DateTimeImmutable('-4 day'),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(SiteDraft::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function mediumNameProvider(): array
    {
        return [
            'web' => ['web'],
            'metaverse' => ['metaverse'],
        ];
    }

    public function testHandleSitesDraft(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        Site::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-4 day'),
                'medium' => MediumName::Web,
                'status' => Site::STATUS_DRAFT,
                'updated_at' => new DateTimeImmutable('-4 day'),
                'user_id' => $user,
            ]
        );
        Site::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-4 day'),
                'medium' => MediumName::Metaverse,
                'status' => Site::STATUS_DRAFT,
                'updated_at' => new DateTimeImmutable('-4 day'),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 2);
        Mail::assertQueued(SiteDraft::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleFundsEnds(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        UserLedgerEntry::factory()->create(
            [
                'amount' => 1e12,
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'user_id' => $user,
            ]
        );
        UserLedgerEntry::factory()->create(
            [
                'amount' => 1e15,
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
                'user_id' => $user,
            ]
        );
        Campaign::factory()->create(
            [
                'budget' => 1e11,
                'status' => Campaign::STATUS_ACTIVE,
                'targeting_requires' => [
                    'site' => [
                        'domain' => ['example.com']
                    ]
                ],
                'time_start' => (new DateTimeImmutable('-5 days'))->format(DATE_ATOM),
                'time_end' => null,
                'user_id' => $user,
            ]
        );

        $userWithoutEmail = User::factory()->create(['email' => null]);
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'user_id' => $userWithoutEmail,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(FundsEnds::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleInactiveUser(): void
    {
        $user = $this->initUserWithConfirmedEmail();

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(InactiveUser::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleInactiveUserWhoDeposit(): void
    {
        $user = $this->initUserWithConfirmedEmail();
        UserLedgerEntry::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-4 days'),
                'amount' => 1e12,
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'updated_at' => new DateTimeImmutable('-4 days'),
                'user_id' => $user,
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(InactiveUserWhoDeposit::class, fn($mail) => $mail->hasTo($user->email));
    }

    /**
     * @dataProvider userInactiveForLongTimeProvider
     */
    public function testHandleUserInactiveForLongTime(array $userData): void
    {
        /** @var User $user */
        $user = User::factory()->create($userData);

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(InactiveUserExtend::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function userInactiveForLongTimeProvider(): array
    {
        return [
            'last activity 1 month ago' => [
                [
                    'created_at' => new DateTimeImmutable('-1 month'),
                    'email' => 'user@example.com',
                    'email_confirmed_at' => new DateTimeImmutable('-1 month'),
                    'last_active_at' => new DateTimeImmutable('-1 month'),
                    'updated_at' => new DateTimeImmutable('-1 month'),
                ],
            ],
            'updated 1 month ago' => [
                [
                    'created_at' => new DateTimeImmutable('-1 month'),
                    'email' => 'user@example.com',
                    'email_confirmed_at' => new DateTimeImmutable('-1 month'),
                    'last_active_at' => null,
                    'updated_at' => new DateTimeImmutable('-1 month'),
                ],
            ],
        ];
    }

    public function testHandleInactiveAdvertiser(): void
    {
        /** @var User $user */
        $user = User::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-5 days'),
                'email' => 'user@example.com',
                'email_confirmed_at' => new DateTimeImmutable('-5 days'),
                'is_publisher' => 0,
                'last_active_at' => new DateTimeImmutable('-1 day'),
                'updated_at' => new DateTimeImmutable('-5 days'),
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(InactiveAdvertiser::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleInactivePublisher(): void
    {
        /** @var User $user */
        $user = User::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-5 days'),
                'email' => 'user@example.com',
                'email_confirmed_at' => new DateTimeImmutable('-5 days'),
                'is_advertiser' => 0,
                'last_active_at' => new DateTimeImmutable('-1 day'),
                'updated_at' => new DateTimeImmutable('-5 days'),
            ]
        );

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertQueued(Mailable::class, 1);
        Mail::assertQueued(InactivePublisher::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testHandleActiveAdvertiser(): void
    {
        $user = $this->initUserWithConfirmedEmail();
        Campaign::factory()->create(['user_id' => $user]);

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    public function testHandleActivePublisher(): void
    {
        $user = $this->initUserWithConfirmedEmail();
        Site::factory()->create(['user_id' => $user]);

        $this->artisan('ops:email-notifications:send')
            ->assertExitCode(0);

        Mail::assertNothingQueued();
    }

    private function initUserWithConfirmedEmail(): User
    {
        return User::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-5 days'),
                'email' => 'user@example.com',
                'email_confirmed_at' => new DateTimeImmutable('-5 days'),
                'last_active_at' => new DateTimeImmutable('-1 day'),
                'updated_at' => new DateTimeImmutable('-5 days'),
            ]
        );
    }
}

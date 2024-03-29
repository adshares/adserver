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

namespace Adshares\Adserver\Console\Commands;

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
use Adshares\Adserver\Models\AdvertiserBudget;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NotificationEmailLog;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\ViewModel\NotificationEmailCategory;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\Mail;

class EmailNotificationsSendCommand extends BaseCommand
{
    protected $signature = 'ops:email-notifications:send';
    protected $description = 'Sends email notifications to advertisers and publishers';

    public function __construct(
        Locker $locker,
        private readonly CampaignRepository $campaignRepository,
        private readonly ExchangeRateReader $exchangeRateReader,
    ) {
        parent::__construct($locker);
    }

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');
            return self::FAILURE;
        }

        $this->info('Start command ' . $this->signature);

        $this->notifyAboutCampaigns();
        $this->notifyAboutSites();
        $this->notifyAboutFunds();
        $this->notifyAboutInactivity();

        $this->info('Finished command ' . $this->signature);

        return self::SUCCESS;
    }

    private function notifyAboutCampaigns(): void
    {
        $now = new DateTimeImmutable();

        $campaigns = $this->campaignRepository->fetchDraftCampaignsCreatedBefore($now->modify('-3 days'));
        foreach ($campaigns as $campaign) {
            if (
                null !== $campaign->user->email &&
                null === NotificationEmailLog::fetch(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignDraft,
                    ['campaignId' => $campaign->id],
                )
            ) {
                Mail::to($campaign->user->email)->queue(new CampaignDraft($campaign));
                NotificationEmailLog::register(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignDraft,
                    null,
                    ['campaignId' => $campaign->id],
                );
            }
        }

        $campaigns = $this->campaignRepository->fetchLastCampaignsEndedBefore($now->modify('-2 weeks'));
        foreach ($campaigns as $campaign) {
            if (
                null !== $campaign->user->email &&
                null === NotificationEmailLog::fetch(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignEndedExtend,
                )
            ) {
                Mail::to($campaign->user->email)->queue(new CampaignEndedExtend());
                NotificationEmailLog::register(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignEndedExtend,
                );
            }
        }

        $campaigns = $this->campaignRepository->fetchCampaignsWhichEndBetween(
            $now,
            $now->modify('+3 days'),
        );
        foreach ($campaigns as $campaign) {
            if (
                null !== $campaign->user->email &&
                null === NotificationEmailLog::fetch(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignEnds,
                    ['campaignId' => $campaign->id],
                )
            ) {
                Mail::to($campaign->user->email)->queue(new CampaignEnds($campaign));
                NotificationEmailLog::register(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignEnds,
                    $now->modify('+3 days'),
                    ['campaignId' => $campaign->id],
                );
            }
        }

        $campaigns = $this->campaignRepository->fetchCampaignsWhichEndBetween(
            $now->modify('-2 weeks'),
            $now,
        );
        foreach ($campaigns as $campaign) {
            if (
                null !== $campaign->user->email &&
                null === NotificationEmailLog::fetch(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignEnded,
                    ['campaignId' => $campaign->id],
                )
            ) {
                Mail::to($campaign->user->email)->queue(new CampaignEnded($campaign));
                NotificationEmailLog::register(
                    $campaign->user->id,
                    NotificationEmailCategory::CampaignEnded,
                    null,
                    ['campaignId' => $campaign->id],
                );
            }
        }
    }

    private function notifyAboutFunds(): void
    {
        $blockades = Campaign::fetchRequiredBudgetsPerUser();

        $appCurrency = Currency::from(config('app.currency'));
        $exchangeRate = match ($appCurrency) {
            Currency::ADS => $this->exchangeRateReader->fetchExchangeRate(),
            default => ExchangeRate::ONE($appCurrency),
        };

        $blockades->each(static function (AdvertiserBudget $budget, int $userId) use ($exchangeRate) {
            $user = (new User())->find($userId);
            if (null === $user->email) {
                return;
            }
            $walletBalance = $user->getWalletBalance();
            $bonusBalance = $user->getBonusBalance();

            $periodCount = 24 * 5;// 5 days
            $requiredBonus = $exchangeRate->toClick($budget->bonusable()) * $periodCount;
            $requiredTotal = $exchangeRate->toClick($budget->total()) * $periodCount;
            $requiredTotal -= min($bonusBalance, $requiredBonus);

            if ($walletBalance < $requiredTotal) {
                Mail::to($user->email)->queue(new FundsEnds());
                NotificationEmailLog::register(
                    $userId,
                    NotificationEmailCategory::FundsEnds,
                    new DateTimeImmutable('+5 days'),
                );
            }
        });
    }

    private function notifyAboutInactivity(): void
    {
        $users = User::fetchInactiveUsersWithEmailsCreatedBefore(new DateTimeImmutable('-3 days'));
        $users->each(static function (User $user) {
            if (null !== NotificationEmailLog::fetch($user->id, NotificationEmailCategory::InactiveUserExtend)) {
                return;
            }

            $lastActivity = $user->last_active_at ?? $user->updated_at;
            if (new DateTimeImmutable('-2 weeks') > $lastActivity) {
                Mail::to($user->email)->queue(new InactiveUserExtend());
                NotificationEmailLog::register($user->id, NotificationEmailCategory::InactiveUserExtend);
                return;
            }

            if (null !== NotificationEmailLog::fetch($user->id, NotificationEmailCategory::InactiveUserWhoDeposit)) {
                return;
            }

            if (
                null !== ($deposit = UserLedgerEntry::fetchFirstDeposit($user->id)) &&
                new DateTimeImmutable('-3 days') > $deposit->created_at
            ) {
                Mail::to($user->email)->queue(new InactiveUserWhoDeposit());
                NotificationEmailLog::register($user->id, NotificationEmailCategory::InactiveUserWhoDeposit);
                return;
            }

            if (null === NotificationEmailLog::fetch($user->id, NotificationEmailCategory::InactiveUser)) {
                $mailable = null;
                if ($user->isAdvertiser()) {
                    if ($user->isPublisher()) {
                        $mailable = new InactiveUser();
                    } else {
                        $mailable = new InactiveAdvertiser();
                    }
                } elseif ($user->isPublisher()) {
                    $mailable = new InactivePublisher();
                }
                if (null !== $mailable) {
                    Mail::to($user->email)->queue($mailable);
                    NotificationEmailLog::register($user->id, NotificationEmailCategory::InactiveUser);
                }
            }
        });
    }

    private function notifyAboutSites(): void
    {
        $sites = Site::fetchDraftSitesCreatedBefore(new DateTimeImmutable('-3 days'));
        $notifiedUserIds = [];
        foreach ($sites as $site) {
            $user = $site->user;
            if (isset($notifiedUserIds[$site->medium][$user->id])) {
                continue;
            }
            $notifiedUserIds[$site->medium][$user->id] = true;
            if (
                null !== $user->email &&
                null === NotificationEmailLog::fetch(
                    $user->id,
                    NotificationEmailCategory::SiteDraft,
                    ['siteId' => $site->id],
                )
            ) {
                Mail::to($user->email)->queue(new SiteDraft($site->medium));
                NotificationEmailLog::register(
                    $user->id,
                    NotificationEmailCategory::SiteDraft,
                    null,
                    ['siteId' => $site->id],
                );
            }
        }
    }
}

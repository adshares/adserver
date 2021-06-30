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
use Adshares\Adserver\Mail\SiteVerified;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Publisher\SiteCategoriesValidator;
use Adshares\Common\Application\Dto\PageRank;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SiteRankUpdateCommand extends BaseCommand
{
    private const CHUNK_SIZE = 200;

    private const EMAIL_NOTIFICATION_RANK_MINIMAL = 0.1;

    protected $signature = 'ops:supply:site-rank:update {--A|all : Update all sites}';

    protected $description = "Updates sites' rank";

    /** @var AdUser */
    private $adUser;

    /** @var SiteCategoriesValidator */
    private $siteCategoriesValidator;

    /** @var string */
    private $siteBaseUrl;

    /** @var array */
    private $mails = [];

    /** @var DateTime */
    private $notificationDateTimeThreshold;

    public function __construct(Locker $locker, AdUser $adUser, SiteCategoriesValidator $siteCategoriesValidator)
    {
        parent::__construct($locker);

        $this->adUser = $adUser;
        $this->siteCategoriesValidator = $siteCategoriesValidator;
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->name . ' already running');

            return;
        }

        $this->info('Start command ' . $this->name);

        $lastId = 0;
        $isOptionAll = (bool)$this->option('all');
        $this->siteBaseUrl = config('app.adpanel_url') . '/publisher/site/';
        $this->notificationDateTimeThreshold =
            Config::fetchDateTime(Config::SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD);

        do {
            if ($isOptionAll) {
                $sites = Site::fetchAll($lastId, self::CHUNK_SIZE);
            } else {
                $sites = Site::fetchInVerification($lastId, self::CHUNK_SIZE);
            }

            $this->update($sites);

            $lastId = $sites->last()->id ?? 0;
        } while ($sites->count() === self::CHUNK_SIZE);

        $this->sendEmails();

        $this->info('Finish command ' . $this->name);
    }

    private function update(Collection $sites): void
    {
        $urls = [];
        foreach ($sites as $index => $site) {
            /** @var Site $site */
            $url = $site->url;
            $categories = $site->categories ?? $site->categories_by_user;

            if (!$url || !$categories) {
                continue;
            }
            $urls[$index] = [
                'url' => $url,
                'categories' => $categories,
            ];
        }

        if (empty($urls)) {
            return;
        }

        try {
            $results = $this->adUser->fetchPageRankBatch($urls);
        } catch (UnexpectedClientResponseException $unexpectedClientResponseException) {
            $this->error($unexpectedClientResponseException->getMessage());

            return;
        } catch (RuntimeException $exception) {
            $this->warn($exception->getMessage());

            return;
        }

        DB::beginTransaction();
        foreach ($results as $index => $result) {
            $site = $sites->get($index);
            if (null === $site) {
                $this->warn(sprintf('Invalid index (%s) in response', $index));

                continue;
            }

            if (isset($result['error'])) {
                $this->warn(
                    sprintf('Error for an URL (%s) from site id (%d) (%s)', $site->url, $site->id, $result['error'])
                );

                continue;
            }

            if (!isset($result['rank']) || !isset($result['info'])) {
                $this->warn(
                    sprintf('Missing `rank` or `info` for an URL (%s) from site id (%d)', $site->url, $site->id)
                );

                continue;
            }

            $pageRank = new PageRank($result['rank'], $result['info']);
            if ($pageRank->getRank() !== $site->rank || $pageRank->getInfo() !== $site->info) {
                if (
                    AdUser::PAGE_INFO_UNKNOWN === $site->info
                    && AdUser::PAGE_INFO_UNKNOWN !== $pageRank->getInfo()
                    && $pageRank->getRank() >= self::EMAIL_NOTIFICATION_RANK_MINIMAL
                    && $site->created_at > $this->notificationDateTimeThreshold
                ) {
                    $this->mails[$site->user_id][] = [
                        'name' => $site->name,
                        'url' => $this->siteBaseUrl . $site->id,
                    ];
                }

                $site->updateWithPageRank($pageRank);
            }
            if (isset($result['categories'])) {
                try {
                    $categories = $this->siteCategoriesValidator->processCategories($result['categories']);
                    $site->updateCategories($categories);
                } catch (InvalidArgumentException $exception) {
                    $this->warn(
                        sprintf(
                            'Invalid `categories` for an URL (%s) from site id (%d). Check targeting taxonomy.',
                            $site->url,
                            $site->id
                        )
                    );
                }
            }
        }
        DB::commit();
    }

    private function sendEmails(): void
    {
        if (empty($this->mails)) {
            return;
        }

        $userIds = array_keys($this->mails);
        $users = User::fetchByIds($userIds);

        foreach ($this->mails as $userId => $sites) {
            $email = $users->get($userId)->email;

            Mail::to($email)->send(new SiteVerified($sites));
        }
    }
}

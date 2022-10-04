<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

use Adshares\Ads\AdsClient;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Entity\Broadcast;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Http\Response\InfoResponse;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Config\AppMode;
use Adshares\Network\BroadcastableUrl;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

class AdsFetchHosts extends BaseCommand
{
    /**
     * Length of block in seconds
     */
    private const BLOCK_TIME = 512;
    /**
     * Period in seconds which will be searched for broadcast
     */
    private const BROADCAST_PERIOD = 12 * 3600; //12 hours

    protected $signature = 'ads:fetch-hosts';
    protected $description = 'Fetches Demand AdServers';

    public function __construct(Locker $locker, private readonly DemandClient $client)
    {
        parent::__construct($locker);
    }

    public function handle(AdsClient $adsClient): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');
            return;
        }

        $this->info('Start command ' . $this->signature);

        $timeNow = time();
        $timeBlock = $this->getTimeOfFirstBlock($timeNow);

        $progressBar = $this->output->createProgressBar((int)floor(self::BROADCAST_PERIOD / self::BLOCK_TIME) + 1);
        $progressBar->start();
        while ($timeBlock <= $timeNow - self::BLOCK_TIME) {
            $blockId = dechex($timeBlock);
            $this->handleBlock($adsClient, $blockId);
            $timeBlock += self::BLOCK_TIME;
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->newLine();

        $this->comment('Cleaning old hosts...');
        $removed = $this->removeOldHosts();
        $this->info($removed > 0 ? sprintf('Removed %d hosts', $removed) : 'Nothing to clean');

        $this->info('Finished command ' . $this->signature);
    }

    private function getTimeOfFirstBlock(int $timeNow): int
    {
        $timeStart = $timeNow - self::BROADCAST_PERIOD;
        $secondsAfterPrevBlock = $timeStart % self::BLOCK_TIME;

        if ($secondsAfterPrevBlock === 0) {
            $timeBlock = $timeStart - self::BLOCK_TIME;
        } else {
            $timeBlock = $timeStart - $secondsAfterPrevBlock;
        }

        return $timeBlock;
    }

    private function handleBlock(AdsClient $adsClient, string $blockId): void
    {
        try {
            $resp = $adsClient->getBroadcast($blockId);
            $broadcastArray = $resp->getBroadcast();

            foreach ($broadcastArray as $broadcast) {
                $this->handleBroadcast($broadcast);
            }
        } catch (CommandException $commandException) {
            $code = $commandException->getCode();
            if (CommandError::BROADCAST_NOT_READY === $code) {
                Log::warning(sprintf('Error %s: Broadcast not ready for block %s', $code, $blockId));
            } else {
                Log::error(sprintf('Error %s: Unexpected error for block %s', $code, $blockId));
            }
        }
    }

    private function handleBroadcast(Broadcast $broadcast): void
    {
        $address = $broadcast->getAddress();
        $time = new DateTimeImmutable('@' . $broadcast->getTime()->getTimestamp());

        try {
            $url = BroadcastableUrl::fromHex($broadcast->getMessage());
            Log::debug(sprintf('Fetching %s', $url->toString()));

            $info = $this->client->fetchInfo($url);
            $this->validateInfoModule($info);
            Log::debug(sprintf('Got %s', $url->toString()));

            $error = $this->getErrorFromInfo($info, $address);
            $host = NetworkHost::registerHost($address, $url->toString(), $info, $time, $error);
            Log::debug(sprintf('Stored %s as #%d', $url->toString(), $host->id));
        } catch (RuntimeException | UnexpectedClientResponseException $exception) {
            Log::debug(sprintf('[%s] {%s}', $url ?? '', $exception->getMessage()));
        }
    }

    private function validateInfoModule(Info $info): void
    {
        if (InfoResponse::ADSHARES_MODULE_NAME !== $info->getModule()) {
            throw new RuntimeException(sprintf('Info for invalid module: %s', $info->getModule()));
        }
    }

    private function getErrorFromInfo(Info $info, string $address): ?string
    {
        $adsAddress = $info->getAdsAddress();
        if (!$adsAddress) {
            return 'Info has empty address';
        }
        if ($adsAddress !== $address) {
            return 'Info address does not match broadcast';
        }
        if (AppMode::INITIALIZATION === $info->getAppMode()) {
            return 'Ad server is in initialization mode';
        }
        return null;
    }

    private function removeOldHosts(): int
    {
        $period = new DateTimeImmutable('-24 hours');
        return NetworkHost::deleteBroadcastedBefore($period);
    }
}

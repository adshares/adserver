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

use Adshares\Ads\AdsClient;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Entity\Broadcast;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Http\Response\InfoResponse;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Network\BroadcastableUrl;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
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
    private const PERIOD = 43200;//12 hours = 12 * 60 * 60 s

    /** @var string */
    protected $signature = 'ads:fetch-hosts';

    /** @var string */
    protected $description = 'Fetches Demand AdServers';

    /** @var DemandClient */
    private $client;

    public function __construct(Locker $locker, DemandClient $client)
    {
        $this->client = $client;

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

        $progressBar = $this->output->createProgressBar(floor(self::PERIOD / self::BLOCK_TIME) + 1);
        $progressBar->start();
        while ($timeBlock <= $timeNow - self::BLOCK_TIME) {
            $blockId = dechex($timeBlock);
            $this->handleBlock($adsClient, $blockId);

            $timeBlock += self::BLOCK_TIME;
            $progressBar->advance();
        }
        $progressBar->finish();

        $this->info('Finished command ' . $this->signature);
    }

    private function getTimeOfFirstBlock(int $timeNow): int
    {
        $timeStart = $timeNow - self::PERIOD;
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
        } catch (CommandException $ce) {
            $code = $ce->getCode();
            if (CommandError::BROADCAST_NOT_READY === $code) {
                Log::warning("Error $code: Broadcast not ready for block $blockId");
            } else {
                Log::error("Error $code: Unexpected error for block $blockId");
            }
        }
    }

    private function handleBroadcast(Broadcast $broadcast): void
    {
        $address = $broadcast->getAddress();
        $time = $broadcast->getTime();

        try {
            $url = BroadcastableUrl::fromHex($broadcast->getMessage());
            Log::debug("Fetching {$url->toString()}");

            $info = $this->client->fetchInfo($url);

            $this->validateInfo($info, $address);

            Log::debug("Got {$url->toString()}");

            $host = NetworkHost::registerHost($address, $info, $time);

            Log::debug("Stored {$url->toString()} as #{$host->id}");
        } catch (RuntimeException | UnexpectedClientResponseException $exception) {
            $url = $url ?? '';
            Log::debug("[$url] {$exception->getMessage()}");
        }
    }

    private function validateInfo(Info $info, string $address): void
    {
        if (InfoResponse::ADSHARES_MODULE_NAME !== $info->getModule()) {
            throw new RuntimeException(sprintf('Info for invalid module: %s', $info->getModule()));
        }

        $adsAddress = $info->getAdsAddress();

        if (!$adsAddress) {
            throw new RuntimeException('Info has empty address');
        }

        if ($adsAddress !== $address) {
            throw new RuntimeException(
                sprintf('Info has different address than broadcast: %s !== %s', $adsAddress, $address)
            );
        }
    }
}

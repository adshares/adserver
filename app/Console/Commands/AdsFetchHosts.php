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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Entity\Broadcast;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Illuminate\Console\Command;
use RuntimeException;
use function parse_url;
use function strlen;
use function substr;

class AdsFetchHosts extends Command
{
    use LineFormatterTrait;

    /**
     * Length of block in seconds
     */
    const BLOCK_TIME = 512;

    /**
     * Period is seconds which will be searched for broadcast
     */
    const PERIOD = 43200;//12 hours = 12 * 60 * 60 s

    /**
     * @var string
     */
    protected $signature = 'ads:fetch-hosts';

    /**
     * @var string
     */
    protected $description = 'Fetches Demand AdServers';

    /** @var DemandClient */
    private $client;

    public function __construct(DemandClient $client)
    {
        $this->client = $client;

        parent::__construct();
    }

    /**
     * @param AdsClient $adsClient
     *
     * @return int
     */
    public function handle(AdsClient $adsClient): void
    {
        $this->info('Start command '.$this->signature);

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

        $this->info('Finished command '.$this->signature);
    }

    /**
     * @param int $timeNow
     *
     * @return int
     */
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

    /**
     * @param AdsClient $adsClient
     * @param string $blockId
     */
    private function handleBlock(AdsClient $adsClient, string $blockId): void
    {
        try {
            $resp = $adsClient->getBroadcast($blockId);
            $broadcastArray = $resp->getBroadcast();

            foreach ($broadcastArray as $broadcast) {
                /** @var $broadcast Broadcast */
                $this->handleBroadcast($broadcast);
            }
        } catch (CommandException $ce) {
            $code = $ce->getCode();
            if (CommandError::BROADCAST_NOT_READY === $code) {
                $this->info("Error $code: Broadcast not ready for block $blockId");
            } else {
                $this->info("Error $code: Unexpected error for block $blockId");
            }
        }
    }

    /**
     * @param $broadcast
     */
    private function handleBroadcast(Broadcast $broadcast): void
    {
        $message = urldecode($this->hexToStr($broadcast->getMessage()));

        if (substr($message, 0, strlen(AdsBroadcastHost::BROADCAST_PREFIX))
            !== AdsBroadcastHost::BROADCAST_PREFIX) {
            return;
        }

        $message = trim(substr($message, strlen(AdsBroadcastHost::BROADCAST_PREFIX)));

        // TODO check if message is valid host
        if (empty($message)) {
            return;
        }

        $address = $broadcast->getAddress();
        $time = $broadcast->getTime();

        $info = null;
        $infoUrl = $this->prepareInfoUrl($message);

        try {
            $info = $this->client->fetchInfo($infoUrl);
        } catch (UnexpectedClientResponseException $exception) {
            $this->info(sprintf('Demand server `%s` does not support `/info` endpoint.', $infoUrl));
        } catch (RuntimeException $exception) {
            $this->error(sprintf('Could not import info data (%s) from server `%s`.', $exception->getMessage(), $infoUrl));
        }

        NetworkHost::registerHost($address, $message, $info, $time);
    }

    private function prepareInfoUrl(string $hostUrl): string
    {
        $parsedUrl = parse_url($hostUrl);
        $path = $parsedUrl['path'] ?? '';

        if ($path === '/info') {
            return $path;
        }

        return $hostUrl . '/info';
    }

    /**
     * @param string $hex
     *
     * @return string
     */
    private function hexToStr(string $hex): string
    {
        $string = '';
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $string .= chr(hexdec($hex[$i].$hex[$i + 1]));
        }

        return $string;
    }
}

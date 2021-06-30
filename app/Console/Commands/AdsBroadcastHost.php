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
use Adshares\Adserver\Console\Locker;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\UrlInterface;
use Adshares\Network\Broadcast;
use Adshares\Network\BroadcastableUrl;

use function route;

class AdsBroadcastHost extends BaseCommand
{
    /**
     * @var string
     */
    protected $signature = 'ads:broadcast-host';

    /**
     * @var string
     */
    protected $description = 'Sends AdServer host address as broadcast message to blockchain';

    /**
     * @var UrlInterface
     */
    private $infoApiUrl;

    public function __construct(Locker $locker)
    {
        parent::__construct($locker);

        $this->infoApiUrl = new SecureUrl(route('app.infoEndpoint'));
    }

    public function handle(AdsClient $adsClient): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('Start command ' . $this->signature);

        $url = new BroadcastableUrl($this->infoApiUrl);
        $command = new Broadcast($url);

        $response = $adsClient->runTransaction($command);

        $txId = $response->getTx()->getId();

        $this->info("Url ($url) broadcast successfully. TxId: $txId");
    }
}

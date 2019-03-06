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
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Common\Domain\ValueObject\BroadcastableUrl;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Network\Broadcast;
use Illuminate\Console\Command;

class AdsBroadcastHost extends Command
{
    use LineFormatterTrait;

    /**
     * @var string
     */
    protected $signature = 'ads:broadcast-host';

    /**
     * @var string
     */
    protected $description = 'Sends AdServer host address as broadcast message to blockchain';

    /**
     * @var Url
     */
    private $infoApiUrl;

    public function __construct()
    {
        parent::__construct();

        $this->infoApiUrl = new Url((string)config('app.adserver_info_url'));
    }

    /**
     * @param AdsClient $adsClient
     *
     * @return int
     */
    public function handle(AdsClient $adsClient): void
    {
        $this->info('Start command '.$this->signature);

        $command = new Broadcast(new BroadcastableUrl($this->infoApiUrl));

        $response = $adsClient->runTransaction($command);

        $txId = $response->getTx()->getId();

        $this->info("Message broadcast successfully. TxId: $txId");
    }
}

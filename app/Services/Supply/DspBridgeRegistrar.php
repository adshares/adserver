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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\NetworkHost;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

class DspBridgeRegistrar
{
    private const INFO_JSON_PATH = '/info.json';
    private const DSP_BRIDGE_MODULE_NAME = 'dsp-bridge';

    public function __construct(private readonly DemandClient $demandClient)
    {
    }

    public function registerAsNetworkHost(): bool
    {
        if (
            null === ($accountAddress = config('app.dsp_bridge_account_address'))
            || null === ($url = config('app.dsp_bridge_url'))
        ) {
            return false;
        }
        $url = $url . self::INFO_JSON_PATH;

        if (!AccountId::isValid($accountAddress, true)) {
            Log::error('DSP bridge provider registration failed: configured account address is not valid');
            return false;
        }
        try {
            $infoUrl = new Url($url);
        } catch (RuntimeException $exception) {
            Log::error(sprintf('DSP bridge provider registration failed: %s', $exception->getMessage()));
            return false;
        }

        try {
            $info = $this->demandClient->fetchInfo($infoUrl);
        } catch (UnexpectedClientResponseException $exception) {
            Log::error(sprintf('DSP bridge provider registration failed: %s', $exception->getMessage()));
            return false;
        }
        if ($info->getModule() !== self::DSP_BRIDGE_MODULE_NAME) {
            Log::error(
                sprintf('DSP bridge provider registration failed: Info for invalid module: %s', $info->getModule())
            );
            return false;
        }
        if ($info->getAdsAddress() !== $accountAddress) {
            Log::error('DSP bridge provider registration failed: Info address does not match');
            return false;
        }
        $host = NetworkHost::registerHost($accountAddress, $url, $info, new DateTimeImmutable());
        Log::debug(sprintf('Stored %s as #%d', $url, $host->id));
        return true;
    }
}

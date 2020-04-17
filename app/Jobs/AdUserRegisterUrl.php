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

namespace Adshares\Adserver\Jobs;

use Adshares\Adserver\Exceptions\JobException;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AdUserRegisterUrl implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    /** @var string */
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function handle(AdUser $adUser): void
    {
        Log::debug(sprintf('[AdUserRegisterUrl] (%s)', $this->url));
        try {
            $adUser->fetchPageRank($this->url);
        } catch (UnexpectedClientResponseException|RuntimeException $exception) {
            Log::warning(
                sprintf(
                    '[AdUserRegisterUrl] Fetch exception for (%s) (%s)',
                    $this->url,
                    $exception->getMessage()
                )
            );

            throw new JobException($exception->getMessage());
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error(
            sprintf(
                '[AdUserRegisterUrl] Job failed for (%s) with an exception (%s)',
                $this->url,
                $exception->getMessage()
            )
        );
    }
}

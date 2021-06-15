<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Models\ServeDomain;
use Adshares\Common\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class UpdateServeDomainsCommand extends BaseCommand
{
    protected $signature = 'ops:serve-domains:update';

    protected $description = 'Updates the serve domain registry';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('[UpdateServeDomains] Start command ' . $this->signature);

        $main_js_tld = config('app.main_js_tld');

        if ($main_js_tld) {
            $jsPath = public_path('-/main.js');
            $params = [
                config('app.main_js_tld'),
                config('app.adserver_id'),
            ];
            $process = new Process(
                ['nodejs'],
                null,
                null,
                str_replace(
                    [
                        '{{ TLD }}',
                        '{{ SELECTOR }}',
                    ],
                    $params,
                    file_get_contents($jsPath)
                )
            );

            if ($process->run() === 0) {
                $url = trim($process->getOutput());
                ServeDomain::upsert($url);
            } else {
                throw new RuntimeException($process->getErrorOutput());
            }
        } else {
            ServeDomain::upsert((string)config('app.serve_base_url'));
        }
        ServeDomain::clear();

        $this->info('[UpdateServeDomains] Finish command ' . $this->signature);
    }
}

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

namespace Adshares\Adserver\Console\Commands;

use BrowscapPHP\BrowscapUpdater;
use BrowscapPHP\Helper\LoggerHelper;
use Doctrine\Common\Cache\FilesystemCache;
use Illuminate\Console\Command;
use Roave\DoctrineSimpleCache\SimpleCacheAdapter;
use Symfony\Component\Console\Output\NullOutput;

class BrowsercapUpdaterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'browsercap:updater';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update browsercap cache';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // TODO: refactor into browsercap service

        $logger = LoggerHelper::createDefaultLogger(new NullOutput());

        $fileCache = new FilesystemCache(storage_path('framework/cache/browscap'));
        $cache = new SimpleCacheAdapter($fileCache);

        $browscap = new BrowscapUpdater($cache, $logger);
        $browscap->fetch(storage_path('framework/cache/browscap').'/browsecap.ini');
        $browscap->convertFile(storage_path('framework/cache/browscap').'/browsecap.ini');

        $this->info('Browsercap cache updated');
    }
}

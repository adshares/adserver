<?php

namespace Adshares\Adserver\Console\Commands;

use Illuminate\Console\Command;

use BrowscapPHP\Browscap;
use BrowscapPHP\BrowscapUpdater;

use Symfony\Component\Console\Output\NullOutput;
use BrowscapPHP\Helper\LoggerHelper;
use Doctrine\Common\Cache\FilesystemCache;
use Roave\DoctrineSimpleCache\SimpleCacheAdapter;

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // TODO: refactor into browsercap service

        $logger = LoggerHelper::createDefaultLogger(new NullOutput);

        $fileCache = new FilesystemCache(storage_path('framework/cache/browscap'));
        $cache = new SimpleCacheAdapter($fileCache);

        $browscap = new BrowscapUpdater($cache, $logger);
        $browscap->fetch(storage_path('framework/cache/browscap').'/browsecap.ini');
        $browscap->convertFile(storage_path('framework/cache/browscap').'/browsecap.ini');

        $this->info('Browsercap cache updated');
    }
}

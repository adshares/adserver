<?php

namespace Adshares\Adserver\Console\Commands;

use Illuminate\Console\Command;

use Adshares\Esc\Esc;

use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Services\Adselect;

class AdserversCrawlCommand extends Command
{
    protected $broadcast=true;
    protected $esc;
    protected $host;
    protected $registerHostsIfBroadcastedLimit = 3600; // seconds
    protected $crawlHostsIfLastSeenLimit = 3600*24*14; // seconds


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adshares:crawl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queries blockchain for available adsevers, downloads available advertisements from each adserver and stores offers in local db. Updates are forwarded to adselect.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->host = config('app.adserver_host');

        // TODO: move to Service
        // ($walletCommand, $workingDir, $address, $secret, $host, $port)
        $this->esc = new Esc(
          config('app.adshares_wallet'),
          config('app.adshares_workdir'),
          config('app.adshares_address'),
          config('app.adshares_secret'),
          config('app.adshares_node_host'),
          config('app.adshares_node_port')
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->readBroadcasts();
        $this->crawlHosts();
    }

    protected function readBroadcasts()
    {
        try {
            $logMessage = $this->esc->getBroadcastLog(time() - $this->registerHostsIfBroadcastedLimit);
            print_r($logMessage);
            $logs = $logMessage->broadcast;
        } catch (\Exception $e) {
            $logs = [
                ['message' => bin2hex($this->host), 'address' => config('app.adshares_address'), 'account_msid' => 1],
            ];
            var_dump($logs);
            // currently it enables to process single own host without blockchain
//             throw $e;
        }

        // TODO: check with Jacek - smell logic bugs
        // TODO: review adseelct json generation filters and double check if this could be copied into adserver database

        if ($logs) {
            foreach ($logs as $log) {
                $host = hex2bin($log['message']);
                if ($this->host == $host) {
                    // found own broadcast. No need to send it again
                    $this->broadcast = false;
                }
                $this->info("Found $host -> {$log['address']}");
                // TODO: extract algo
                if (preg_match('/^([a-z0-9][a-z0-9-]{0,62}\.)+([a-z]{2,})$/i', $host)) {
                    NetworkHost::registerHost(Esc::normalizeAddress($log['address']), $host);
                // TODO: check this with Jacek in adserver symfony code
                    // $nHost->setAccountMsid($log['account_msid']);
                } else {
                    // TODO: debug error log?
                }
            }
        }

        if ($this->broadcast) {
            $this->info("Broadcast own host: $this->host");
            $x = $this->esc->sendBroadcast($this->host);
            var_dump($x);
            // TODO: this fails currently
//             die(print_r($x));
        }
    }

    protected function crawlHosts()
    {
        $crawlTime = time();

        $hosts = NetworkHost::where('last_seen', '>', time() - $this->crawlHostsIfLastSeenLimit)->get();

        $batch = 0;

        // TODO: integrate with adselect
        // $adselectService = $this->getContainer()->has('adselect') ? $this->getContainer()->get('adselect') : null;
        // $adselectService instanceof Adselect;
        // $adselectCmp = [];

        foreach ($hosts as $r) {
            $host = $r->host;
            $this->info("Crawl: $host");

            // status: updated, removed, synced (adselect)
            $inventory = json_decode(file_get_contents("http://{$host}/adshares/inventory/list"), JSON_OBJECT_AS_ARRAY);

            if (empty($inventory)) {
                // TODO: double check empty behaviour
                continue;
            }

            foreach ($inventory['campaigns'] as $campaign_data) {
                $campaign_data['source_host'] = $host;
                $campaign = NetworkCampaign::fromJsonData($campaign_data);//, $existing);

                // $adselectCmp[] = $campaign->getAdselectJson();
                // if ($batch++ == 100) {
                //     if ($adselectService) {
                //         $adselectService->addCampaigns($adselectCmp);
                //         $adselectCmp = [];
                //     }
                //     $em->flush();
                // }
            }

            // if ($adselectService) {
            //     $adselectService->addCampaigns($adselectCmp);
            //     $adselectCmp = [];
            // }
            // $em->flush();
            //
            // if ($adselectService) {
            //     $deleted = $em->createQuery("SELECT u.uuid FROM Adshares\Entity\NetworkCampaign u WHERE u.source_host = :host AND u.source_update_time != :time")
            //       ->setParameter("host", $host)->setParameter("time", $crawlTime)
            //       ->getResult(Query::HYDRATE_SCALAR);
            //
            //     $campaignIds = [];
            //     foreach ($deleted as $r) {
            //         $campaignIds[] = $host . '/'. $r['uuid'];
            //     }
            //     if ($campaignIds) {
            //         $adselectService->deleteCampaigns($campaignIds);
            //     }
            // }
            //
            // $query = $em->createQuery("DELETE FROM Adshares\Entity\NetworkCampaign u WHERE u.source_host = :host AND u.source_update_time != :time");
            // $query->setParameter("host", $host)->setParameter("time", $crawlTime);
            // $query->execute();
        }
    }
}

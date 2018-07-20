<?php

namespace Adshares\Adserver\Console\Commands;

use Illuminate\Console\Command;
use Adshares\Adserver\Services\Adselect;
use Adshares\Ads\AdsClient;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Utilities\AdsUtils;

/**
 * supply adserver.
 *
 * Crawl command =is called periodically. It queries blockchain for available adsevers.
 * It downloads available advertisements from each adserver and stores offers in local db
 * Updates are forwarded to adselect
 */
class AdsharesCrawlCommand extends Command
{
    protected $broadcast = true;
    protected $host;
    protected $registerHostsIfBroadcastedLimit = 3600; // seconds
    protected $crawlHostsIfLastSeenLimit = 3600 * 24 * 14; // seconds

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
    protected $description = 'Queries blockchain for available adsevers, downloads available advertisements '.
    'from each adserver and stores offers in local db. Updates are forwarded to adselect.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->host = config('app.adserver_host');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Adselect $adselect, AdsClient $adsClient)
    {
        $this->readBroadcasts($adsClient);
        $this->crawlHosts($adselect);
    }

    protected function readBroadcasts(AdsClient $adsClient)
    {
        try {
            $logMessage = $adsClient->getBroadcast(time() - $this->registerHostsIfBroadcastedLimit);
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
                    NetworkHost::registerHost(AdsUtils::normalizeAddress($log['address']), $host);
                // TODO: check this with Jacek in adserver symfony code
                    // $nHost->setAccountMsid($log['account_msid']);
                } else {
                    // TODO: debug error log?
                }
            }
        }

        if ($this->broadcast) {
            $this->info("Broadcast own host: $this->host");
            $x = $adsClient->broadcast($this->host);
            var_dump($x);
            // TODO: this fails currently
//             die(print_r($x));
        }
    }

    protected function crawlHosts(Adselect $adselect)
    {
        $crawlTime = time();

        $hosts = NetworkHost::where('last_seen', '>', time() - $this->crawlHostsIfLastSeenLimit)->get();

        $batch = 0;

        $adselectCmp = [];

        foreach ($hosts as $r) {
            $host = $r->host;
            $this->info("STARTING PROCESSING: $host");

            // status: updated, removed, synced (adselect)
            $inventory = json_decode(file_get_contents("http://{$host}/adshares/inventory/list"), JSON_OBJECT_AS_ARRAY);

            if (empty($inventory)) {
                // TODO: double check empty behaviour
                continue;
            }

            $uuids = [];

            foreach ($inventory['campaigns'] as $campaign_data) {
                $campaign_data['source_host'] = $host;
                $campaign = NetworkCampaign::fromJsonData($campaign_data);

                $uuids[] = hex2bin($campaign->uuid);

                $adselectCmp[] = $campaign->getAdselectJson();
                if (100 == $batch++) {
                    if ($adselect) {
                        $adselect->addCampaigns($adselectCmp);
                        $adselectCmp = [];
                    }
                }
            }

            if ($adselect) {
                $adselect->addCampaigns($adselectCmp);
                $adselectCmp = [];

                $forRemoval = NetworkCampaign::where('source_host', $host)->whereNotIn('uuid', $uuids)->get();

                if (empty($forRemoval)) {
                    continue;
                }
                $campaignIds = [];
                foreach ($forRemoval as $c) {
                    $campaignIds[] = $host.'/'.$c->uuid;
                }

                NetworkCampaign::where('source_host', $host)->whereNotIn('uuid', $uuids)->delete();

                $adselect->deleteCampaigns($campaignIds);
            }

            // $query = $em->createQuery("DELETE FROM Adshares\Entity\NetworkCampaign u
            //    WHERE u.source_host = :host AND u.source_update_time != :time");
            // $query->setParameter("host", $host)->setParameter("time", $crawlTime);
            // $query->execute();
            $this->info("FINISHED PROCESSING: $host");
        }
    }
}

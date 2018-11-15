<?php

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\BroadcastCommand;
use Adshares\Ads\Exception\CommandException;
use Illuminate\Console\Command;

class AdsBroadcastHost extends Command
{
    const BROADCAST_PREFIX = 'AdServer.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ads:broadcast-host';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends AdServer host address as broadcast message to blockchain';

    private $host;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->host = config('app.adserver_host');
    }

    /**
     * Execute the console command.
     *
     * @param AdsClient $adsClient
     *
     * @return mixed
     */
    public function handle(AdsClient $adsClient)
    {
        $this->info('Start command '.$this->signature);

        $message = $this->strToHex(urlencode(self::BROADCAST_PREFIX.$this->host));

        $command = new BroadcastCommand($message);
        try {
            $response = $adsClient->runTransaction($command);
            $txid = $response->getTx()->getId();
            $this->info("Broadcast message sent successfully. Txid: [$txid]");
        } catch (CommandException $exc) {
            $this->error('Cannot send broadcast due to error '.$exc->getCode());
        }

        return;
    }

    private function strToHex(string $string): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0'.$hexCode, -2);
        }

        return strToUpper($hex);
    }
}

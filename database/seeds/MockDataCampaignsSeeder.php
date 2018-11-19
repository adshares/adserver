<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\User;
use Illuminate\Database\Seeder;

class MockDataCampaignsSeeder extends Seeder
{
    private $bannerSizes = [
        [728, 90],
        [728, 200],
        [160, 600],
        [230, 600],
    ];

    private static function getRandValue($type)
    {
        switch ($type) {
            case 'browser_name':
                $values = [
                    'chrome',
                    'firefox',
                    'opera',
                    'edge',
                ];
                break;
            case 'platform_name':
                $values = [
                    'win',
                    'linux',
                    'mac',
                ];
                break;
            case 'device_type':
                $values = [
                    'desktop',
                    'tablet',
                    'phone',
                ];
                break;
            case 'inframe':
            case 'keyword_games':
                $values = [
                    '1',
                    '0',
                ];
                break;
            case 'browser_name:version':
                $values = [
                    'chrome:00053',
                    'opera:00009',
                    'firefox:00025',
                ];
                break;
        }

        return $values[array_rand($values, 1)];
    }

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->command->info('[mock] seeding: campaigns');

        if (Campaign::count() > 0) {
            $this->command->error('Campaigns table not empty - seeding only into empty db');

            return 999;
        }

        if (0 == User::count()) {
            $this->command->error('Users must be seeded first');

            return 999;
        }

        $camp_data = MockDataSeeder::mockDataLoad('campaigns-advertisers.json');

        DB::beginTransaction();
        foreach ($camp_data as $i => $r) {
            $u = User::where('email', $r->email)->first();
            if (empty($u)) {
                DB::rollback();
                throw new Exception("User not found <{$r->email}>");
            }

            foreach ($r->campaigns as $cr) {
                $campaign = $this->createCampaign($u, $cr);
                $nc = $this->createNetworkCampaign($cr, $campaign);

                $banners = [];

                $files = glob(base_path('var') . "/{$cr->code}/*.png");

                foreach ($files as $filename) {
                    $b = $this->makeBanner($campaign, getimagesize($filename), $filename);
                    $b->save();
                    $banners[] = $b;
                    $this->command->info(" Added banner - #{$b->id} [{$b->creative_width}x{$b->creative_height}]");
                }

                if (empty($banners)) {
                    foreach ($this->bannerSizes as $size) {
                        $b = $this->makeBanner($campaign, $size);
                        $b->save();
                        $banners[] = $b;
                        $this->command->info(" Added banner - #{$b->id} [{$b->creative_width}x{$b->creative_height}]");
                    }
                }

                // NETWORK BANNERS
                foreach ($banners as $banner) {
                    $b = $this->makeNetworkBanner($banner, $nc);
                    $b->save();
                }

                $this->command->info(" Added - [$campaign->landing_url] for user <{$u->email}>");
            }
        }
        DB::commit();

        $this->command->info('Campaigns mock data seeded - for first user and last ' . ($i) . ' users');
    }

    private function generateBannernPng($id, $width, $height, $text = '')
    {
        $image = \imagecreatetruecolor($width, $height);

        $bgColor = \imagecolorallocate($image, rand(0, 200), rand(0, 200), rand(0, 200));
        $textColor = \imagecolorallocate($image, 0, 0, 0);

        \imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // The text to draw
        $rand = mt_rand(10000, 99999);
        $text = "{$text}\nBID: {$id}\n{$rand}\nW: $width\nH: $height";
        // Replace path by your own font path
        $font = resource_path('fonts/mock-font.ttf');
        $size = 20;

        // Add the text
        \imagettftext($image, $size, 0, 5, $size + 10, $textColor, $font, $text);

        ob_start();
        \imagepng($image);

        return ob_get_clean();
    }

    private function generateBannerHTML($id, $width, $height)
    {
        $img = $this->generateBannernPng($id, $width, $height, 'HTML');
        $base64Image = base64_encode($img);

        $server_url = env('APP_URL');
        $view_js_route = route('demand-view.js');

        //if(!mt_rand(0, 2))        return self::tankHTML();
        return '
        <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
        <html>
        <head>
            <meta http-equiv="content-type" content="text/html; charset=utf-8">
            <meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src \'self\' data: '
            . $server_url
            . ' '
            . $server_url
            . '; frame-src \'self\' data:; script-src \'self\' '
            . $server_url
            . ' '
            . $server_url
            . ' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\';">
        </head>
        <body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" style="background:transparent">
            <script src="'
            . $view_js_route
            . '"></script>
            <a id="adsharesLink">
            <img src="data:image/png;base64,'
            . $base64Image
            . '" width="'
            . $width
            . '" height="'
            . $height
            . '" border="0">
            </a>

        </body>
        </html>
        ';
    }

    private function makeNetworkBanner(Banner $banner, $nc): NetworkBanner
    {
        $serveUrl = route('banner-serve', [
            'id' => $banner,
        ]);

        $b = new NetworkBanner();
        $b->fill([
            'network_campaign_id' => $nc->id,
            'uuid' => uniqid() . '1',
            'creative_type' => 'image',
            'creative_width' => $banner->creative_width,
            'creative_height' => $banner->creative_height,
            'serve_url' => $serveUrl,
            'click_url' => route('banner-click', [
                'id' => $banner->id,
            ]),
            'view_url' => route('banner-view', [
                'id' => $banner->id,
            ]),
        ]);

        return $b;
    }

    private function makeBanner($c, $s = [], $filename = null): Banner
    {
        $t = 'image';
        $b = new Banner();
        $b->fill([
            'campaign_id' => $c->id,
            'creative_type' => $t,
            'creative_width' => $s[0],
            'creative_height' => $s[1],
        ]);

        if (!empty($filename)) {
            $b->creative_contents = file_get_contents($filename);
        } else {
            $b->creative_contents = $this->generateBannernPng(rand(1, 9), $s[0], $s[1], "CID: $c->id");
        }

        return $b;
    }

    private function createCampaign($u, $cr): Campaign
    {
        $campaign = new Campaign();
        $campaign->landing_url = $cr->url;
        $campaign->user_id = $u->id;
        $campaign->name = $cr->name;
        $campaign->budget = $cr->budget_per_hour;
        $campaign->status = 2; // active
        $campaign->targeting_requires = $cr->targeting_requires ?? null;
        $campaign->targeting_excludes = $cr->targeting_excludes ?? null;
        $campaign->classification_status = $cr->classification_status ?? 0;
        $campaign->classification_tags = $cr->classification_tags ?? null;

        $campaign->fill([
            'time_start' => date('Y-m-d H:i:s'),
            'time_end' => date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60),
        ]);
        $campaign->save();

        return $campaign;
    }

    private function createNetworkCampaign($cr, $campaign): NetworkCampaign
    {
        $campaign = new NetworkCampaign();
        $campaign->uuid = uniqid() . '1';
        $campaign->parent_uuid = $campaign->uuid;
        $campaign->landing_url = $cr->url;
        $campaign->max_cpm = $cr->max_cpm;
        $campaign->max_cpc = $cr->max_cpc;
        $campaign->source_host = config('app.url');
        $campaign->source_version = '0.1';
        $campaign->budget_per_hour = $cr->budget_per_hour;
        $campaign->adshares_address = '0001-00000001-0001';

        $campaign->fill([
            'time_start' => date('Y-m-d H:i:s'),
            'time_end' => date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60),
        ]);
        $campaign->save();

        return $campaign;
    }
}

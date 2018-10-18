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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\User;
use Illuminate\Database\Seeder;

class MockDataCampaignsSeeder extends Seeder
{
    private $bannerSizes = [
        [728, 90], [160, 600], [468, 60], [250, 250],
    ];

    private function generateBannernPng($id, $width, $height, $text = '')
    {
        $image = \imagecreatetruecolor($width, $height);

        $bgColor = \imagecolorallocate($image, 0, 0, 240);
        $textColor = \imagecolorallocate($image, 0, 0, 0);

        \imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // The text to draw
        $rand = mt_rand(10000, 99999);
        $text = "{$text}{$width}x{$height}\nID: {$id}\n{$rand}";
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
            <meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src \'self\' data: '.$server_url.' '.$server_url.'; frame-src \'self\' data:; script-src \'self\' '.$server_url.' '.$server_url.' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\';">
        </head>
        <body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" style="background:transparent">
            <script src="'.$view_js_route.'"></script>
            <a id="adsharesLink">
            <img src="data:image/png;base64,'.$base64Image.'" width="'.$width.'" height="'.$height.'" border="0">
            </a>

        </body>
        </html>
        ';
    }

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

            $campaignCount = 1;
            foreach ($r->campaigns as $cr) {
                $c = new Campaign();
                $c->landing_url = $cr->url;
                $c->user_id = $u->id;
                $c->name = "Campaign #$campaignCount";
//                $c->max_cpm = $cr->max_cpm;
//                $c->max_cpc = $cr->max_cpc;
                $c->budget = $cr->budget_per_hour;
                $c->status = 2; // active

                $c->fill([
                    'time_start' => date('Y-m-d H:i:s'),
                    'time_end' => date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60),
                ]);
                $c->save();

                ++$campaignCount;

                // NETWORK CAMPAIGNS
                $nc = new NetworkCampaign();
                $nc->uuid = uniqid().'1';
                $nc->landing_url = $cr->url;
                $nc->max_cpm = $cr->max_cpm;
                $nc->max_cpc = $cr->max_cpc;
                $nc->source_host = config('app.url');
                $nc->budget_per_hour = $cr->budget_per_hour;
                $nc->adshares_address = '0001-00000001-0001';

                $nc->fill([
                    'time_start' => date('Y-m-d H:i:s'),
                    'time_end' => date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60),
                ]);
                $nc->save();

                $banners = [];

                // BANNERS
                for ($bi = 0; $bi < 4; ++$bi) {
                    $t = $bi % 2 ? 'image' : 'html';
                    $s = $this->bannerSizes[array_rand($this->bannerSizes)];
                    $b = new Banner();
                    $b->fill(['campaign_id' => $c->id, 'creative_type' => $t, 'creative_width' => $s[0], 'creative_height' => $s[1]]);
                    $b->creative_contents = 'image' == $t ? $this->generateBannernPng($i, $s[0], $s[1]) : $this->generateBannerHTML($i, $s[0], $s[1]);
                    $b->save();

                    $banners[] = $b->id;
                }

                // NETWORK BANNERS
                for ($bi = 0; $bi < 4; ++$bi) {
                    $uuid = uniqid().'1';
                    $bannerId = rand(min($banners), max($banners));
                    $serveUrl = route('banner-serve', [
                        'id' => $bannerId,
                    ]);

                    $t = $bi % 2 ? 'image' : 'html';
                    $s = $this->bannerSizes[array_rand($this->bannerSizes)];
                    $b = new NetworkBanner();
                    $b->fill([
                        'network_campaign_id' => $nc->id,
                        'uuid' => $uuid,
                        'creative_type' => $t,
                        'creative_width' => $s[0],
                        'creative_height' => $s[1],
                        'serve_url' => $serveUrl,
                        'click_url' => route('log-network-click', [
                            'id' => '',
                            'r' => Utils::urlSafeBase64Encode(config('app.app_url')),
                        ]),
                        'view_url' => route('log-network-view', [
                            'id' => '',
                            'r' => Utils::urlSafeBase64Encode(config('app.app_url')),
                        ]),
                    ]);

//                    $b->creative_contents = 'image' == $t ? $this->generateBannernPng($i, $s[0], $s[1]) : $this->generateBannerHTML($i, $s[0], $s[1]);
                    $b->save();
                }
                $this->command->info(" Added - [$c->landing_url] for user <{$u->email}>");
            }
        }
        DB::commit();

        $this->command->info('Campaigns mock data seeded - for first user and last '.($i).' users');
    }
}

<?php

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
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

        $bgColor = \imagecolorallocate($image, 240, 240, 240);
        $textColor = \imagecolorallocate($image, 0, 0, 0);

        \imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // The text to draw
        $rand = mt_rand(10000, 99999);
        $text = "{$text}{$width}x{$height}\nID: {$id}\n{$rand}";
        // Replace path by your own font path
        $font = resource_path('assets/fonts/mock-font.ttf');
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
            <meta http-equiv="Content-Security-Policy" content="default-src \none\'; img-src \'self\' data: '.$server_url.' '.$server_url.'; frame-src \'self\' data:; script-src \'self\' '.$server_url.' '.$server_url.' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\';">
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

            foreach ($r->campaigns as $cr) {
                $c = new Campaign();
                $c->landing_url = $cr->url;
                $c->user_id = $u->id;
                $c->max_cpm = $cr->max_cpm;
                $c->max_cpc = $cr->max_cpc;
                $c->budget_per_hour = $cr->budget_per_hour;

                $c->fill([
                    'time_start' => date('Y-m-d H:i:s'),
                    'time_end' => date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60),
                ]);
                $c->save();

                // BANNERS

                for ($bi = 0; $bi < 2; ++$bi) {
                    $t = $bi % 2 ? 'image' : 'html';
                    $s = $this->bannerSizes[array_rand($this->bannerSizes)];
                    $b = new Banner();
                    $b->fill(['campaign_id' => $c->id, 'creative_type' => $t, 'creative_width' => $s[0], 'creative_height' => $s[1]]);
                    $b->creative_contents = 'image' == $t ? $this->generateBannernPng($i, $s[0], $s[1]) : $this->generateBannerHTML($i, $s[0], $s[1]);
                    $b->save();
                }
                $this->command->info(" Added - [$c->landing_url] for user <{$u->email}>");
            }
        }
        DB::commit();

        $this->command->info('Campaigns mock data seeded - for first user and last '.($i).' users');
    }
}

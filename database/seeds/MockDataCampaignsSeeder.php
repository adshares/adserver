<?php

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\CampaignExclude;
use Adshares\Adserver\Models\CampaignRequire;
use Adshares\Adserver\Models\User;

use Illuminate\Database\Seeder;

class MockDataCampaignsSeeder extends Seeder
{
    private $bannerSizes = [
        [728, 90], [160, 600], [468, 60], [250, 250]
    ];
    private $paramsInt = [
        "screen_width",
        "screen_height",
    ];
    private $paramsStr = [
        "platform_name",
        "device_type",
        "browser_name",
        "inframe",
        "keyword_games",
    ];

    private function generateBannernPng($id, $width, $height, $text = "")
    {
        $image = \imagecreatetruecolor($width, $height);

        $bgColor = \imagecolorallocate($image, 240, 240, 240);
        $textColor = \imagecolorallocate($image, 0, 0, 0);

        \imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);


        // The text to draw
        $rand = mt_rand(10000, 99999);
        $text = "{$text}{$width}x{$height}\nID: {$id}\n{$rand}";
        // Replace path by your own font path
        $font = resource_path('assets/fonts/font.ttf');
        $size = 20;

        // Add the text
        \imagettftext($image, $size, 0, 5, $size + 10, $textColor, $font, $text);


        ob_start();
        \imagepng($image);

        return ob_get_clean();
    }

    private function generateBannerHTML($id, $width, $height)
    {
        $img = $this->generateBannernPng($id, $width, $height, "HTML");
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
                "chrome",
                "firefox",
                "opera",
                "edge"
                    ];
                break;
            case 'platform_name':
                $values = [
                "win",
                "linux",
                "mac"
                    ];
                break;
            case 'device_type':
                $values = [
                "desktop",
                "tablet",
                "phone"
                    ];
                break;
            case 'inframe':
            case 'keyword_games':
                $values = [
                "1",
                "0"
                    ];
                break;
            case 'browser_name:version':
                $values = [
                "chrome:00053",
                "opera:00009",
                "firefox:00025"
                    ];
                break;
        }

        return $values[array_rand($values, 1)];
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('[mock] seeding: campaigns');

        if (Campaign::count() > 0) {
            $this->command->error('Campaigns already seeded');

            return 999;
        }

        $max = User::count();
        if (empty($max)) {
            $this->command->error('Users must be seeded first');
        }

        $camp_data = MockDataSeeder::mockDataLoad(__DIR__ . '/../mock-data/websites-advertisers.json');
        $camp_cols = array_flip($camp_data->cols);
        $camp_data = $camp_data->data;

        DB::beginTransaction();
        foreach ($camp_data as $i => $r) {

            // CAMPAIGN

            $c = new Campaign;
            $c->landing_url = 'http://'.$r[$camp_cols['url']].'/';
            if ($i) {
                $c->user_id = $i+1+(20-count($camp_data));
            } else {
                $c->user_id = $i+1;
            }
            $c->fill([
              'max_cpm'=>10,'max_cpc'=>100,'budget_per_hour' => mt_rand(10, 1000),
              'time_start' => date('Y-m-d H:i:s'),
              'time_end' => date('Y-m-d H:i:s', time() + 30*24*60*60),
            ]);
            $c->save();

            // BANNERS

            for ($bi=0;$bi<2;$bi++) {
                $t = $bi % 2 ? 'image' : 'html';
                $s = $this->bannerSizes[array_rand($this->bannerSizes)];
                $b = new Banner;
                $b->fill(['campaign_id'=>$c->id,'creative_type'=>$t,'creative_width'=>$s[0],'creative_height'=>$s[1]]);
                $b->creative_contents = $t == 'image' ? $this->generateBannernPng($i, $s[0], $s[1]) : $this->generateBannerHTML($i, $s[0], $s[1]);
                $b->save();
            }

            // A FEW FILTERS

            foreach ($this->paramsInt as $paramInt) {
                if (mt_rand(1, 4) == 1) {
                    continue;
                }
                if ($paramInt == "browser_name:version") {
                    if (mt_rand(1, 2) == 1) {
                        $min = chr(0x00);
                        $max = "chrome:00030";
                    } else {
                        $min = "chrome:00025";
                        $max = chr(0xFF);
                    }
                } else {
                    $min = str_pad(mt_rand(1, 500), 3, '0', STR_PAD_LEFT);
                    $max = str_pad(mt_rand(500, 1000), 3, '0', STR_PAD_LEFT);
                }

                $cm = mt_rand(1, 4) == 1 ? new CampaignRequire() : new CampaignExclude();

                if ($cm instanceof CampaignRequire || (mt_rand(-20, 20)/100) == 0) {
                    $cm->fill(['campaign_id'=>$c->id,'name'=>$paramInt,'min'=>$min,'max'=>$max]);
                    $cm->save();
                }
            }

            // FEW MORE FILTERS

            foreach ($this->paramsStr as $paramStr) {
                if (mt_rand(1, 2) == 1) {
                    continue;
                }

                $cm = mt_rand(1, 4) == 1 ? new CampaignRequire() : new CampaignExclude();

                $min = $max = self::getRandValue($paramStr);

                if ($cm instanceof CampaignRequire || (mt_rand(-10, 10)/100) == 0) {
                    $cm->fill(['campaign_id'=>$c->id,'name'=>$paramInt,'min'=>$min,'max'=>$max]);
                    $cm->save();
                }
            }
        }
        DB::commit();

        $this->command->info('Campaigns mock data seeded - for first user and last ' . ($i) . ' users');
    }
}

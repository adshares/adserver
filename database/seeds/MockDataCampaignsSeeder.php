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
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\ConversionDefinition;
use Illuminate\Database\Seeder;

class MockDataCampaignsSeeder extends Seeder
{
    private $bannerSizes = [
        [728, 90],
        [750, 200],
        [120, 600],
        [160, 600],
    ];

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
        foreach ($camp_data as $i => $mockCampaign) {
            $user = User::where('email', $mockCampaign->email)->first();
            if (empty($user)) {
                DB::rollback();
                throw new Exception("User not found <{$mockCampaign->email}>");
            }

            foreach ($mockCampaign->campaigns as $cr) {
                $campaign = $this->createCampaign($user, $cr);

                if (isset($cr->conversions)) {
                    foreach ($cr->conversions as $conversionData) {
                        $name = $campaign->name . 'Conversion';

                        $conversion = new ConversionDefinition();
                        $conversion->name = $name;
                        $conversion->campaign_id = $campaign->id;
                        $conversion->budget_type = $conversionData->budget_type;
                        $conversion->event_type = $conversionData->event_type;
                        $conversion->type = $conversionData->type;
                        $conversion->value = $conversionData->value ?? null;
                        $conversion->limit = $conversionData->limit ?? null;
                        $conversion->save();
                    }
                }

                $banners = [];

                $files = glob(__DIR__."/assets/{$cr->code}/*.png");

                foreach ($files as $filename) {
                    $b = $this->makeImageBanner($campaign, getimagesize($filename), $filename);
                    $b->save();
                    $banners[] = $b;
                    $this->command->info(" Added banner - #{$b->id} [{$b->getFormattedSize()}]");
                }

                if (empty($banners)) {
                    foreach ($this->bannerSizes as $size) {
                        $b = $this->makeImageBanner($campaign, $size);
                        $b->save();
                        $banners[] = $b;
                        $this->command->info(" Added IMAGE banner - #{$b->id} [{$b->getFormattedSize()}]");
                    }
                }

                foreach ($this->bannerSizes as $size) {
                    $b = $this->makeHtmlBanner($campaign, $size);
                    $b->save();
                    $banners[] = $b;
                    $this->command->info(" Added HTML banner - #{$b->id} [{$b->getFormattedSize()}]");
                }

                $this->command->info(" Added - [$campaign->landing_url] for user <{$user->email}>");
            }
        }
        DB::commit();

        $this->command->info('Campaigns mock data seeded - for first user and last '.($i).' users');
    }

    private function createCampaign($u, $cr): Campaign
    {
        $campaign = factory(Campaign::class)->create(
            [
                'landing_url' => $cr->url,
                'user_id' => $u->id,
                'name' => $cr->name,
                'budget' => $cr->budget_per_hour,
                'max_cpc' => $cr->max_cpc,
                'max_cpm' => $cr->max_cpm,
                'status' => Campaign::STATUS_ACTIVE,
                'targeting_requires' => $cr->targeting_requires ?? null,
                'targeting_excludes' => $cr->targeting_excludes ?? null,
                'classification_status' => $cr->classification_status ?? 0,
                'classification_tags' => $cr->classification_tags ?? null,
            ]
        );

        return $campaign;
    }

    private function makeImageBanner($campaign, $size = [], $filename = null): Banner
    {
        $b = new Banner();
        $b->fill(
            [
                'campaign_id' => $campaign->id,
                'creative_type' => 'image',
                'creative_width' => $size[0],
                'creative_height' => $size[1],
                'name' => (null === $filename) ? 'seed' : basename($filename, '.png'),
                'status' => Banner::STATUS_ACTIVE,
            ]
        );

        if (!empty($filename)) {
            $b->creative_contents = file_get_contents($filename);
        } else {
            $b->creative_contents = $this->generateBannernPng(rand(1, 9), $size[0], $size[1], "CID: $campaign->id");
        }

        return $b;
    }

    private function makeHtmlBanner($campaign, array $size): Banner
    {
        $b = new Banner();
        $b->fill(
            [
                'campaign_id' => $campaign->id,
                'creative_type' => 'html',
                'creative_width' => $size[0],
                'creative_height' => $size[1],
                'creative_contents' => $this->generateBannerHTML(rand(1,9), $size[0], $size[1]),
                'name' => sprintf('Banner HTML %s-%s', $size[0], $size[1]),
                'status' => Banner::STATUS_ACTIVE,
            ]
        );

        return $b;
    }

//    private function makeNetworkBanner(Banner $banner, $nc): NetworkBanner
//    {
//        $serveUrl = route('banner-serve', [
//            'id' => $banner,
//        ]);
//
//        $b = new NetworkBanner();
//        $b->fill([
//            'network_campaign_id' => $nc->id,
//            'uuid' => Uuid::v4(),
//            'type' => 'image',
//            'width' => $banner->creative_width,
//            'height' => $banner->creative_height,
//            'serve_url' => $serveUrl,
//            'click_url' => route('banner-click', [
//                'id' => $banner->id,
//            ]),
//            'view_url' => route('banner-view', [
//                'id' => $banner->id,
//            ]),
//        ]);
//
//        return $b;
//    }

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

        //if(!mt_rand(0, 2))        return self::tankHTML();
        return '
        <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
        <html>
        <head>
            <meta http-equiv="content-type" content="text/html; charset=utf-8">
            <meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src \'self\' data: '
            .$server_url
            .' '
            .$server_url
            .'; frame-src \'self\' data:; script-src \'self\' '
            .$server_url
            .' '
            .$server_url
            .' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\';">
        </head>
        <body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" style="background:transparent">
            <a id="adsharesLink">
            <img src="data:image/png;base64,'
            .$base64Image
            .'" width="'
            .$width
            .'" height="'
            .$height
            .'" border="0">
            </a>

        </body>
        </html>
        ';
    }

//    private function createNetworkCampaign($mockCampaign, $sourceCampaign, $user): NetworkCampaign
//    {
//        $campaign = new NetworkCampaign();
//        $campaign->uuid = Uuid::v4();
//        $campaign->demand_campaign_id = $sourceCampaign->uuid;
//        $campaign->publisher_id = $user->uuid;
//        $campaign->landing_url = $mockCampaign->url;
//        $campaign->max_cpm = $mockCampaign->max_cpm;
//        $campaign->max_cpc = $mockCampaign->max_cpc;
//        $campaign->source_host = config('app.url');
//        $campaign->source_version = '0.1';
//        $campaign->source_address = '0001-00000001-0001';
//        $campaign->source_created_at = new DateTime();
//        $campaign->source_updated_at = new DateTime();
//        $campaign->budget = $mockCampaign->budget_per_hour;
//
//
//        $campaign->fill([
//            'date_start' => date('Y-m-d H:i:s'),
//            'date_end' => date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60),
//        ]);
//        $campaign->save();
//
//        return $campaign;
//    }
}

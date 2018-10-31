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

namespace Adshares\Adserver\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class Simulator extends Controller
{
    private static $zoneSizes = [
        #best
        'leaderboard' => '728x90',
        'medium-rectangle' => '300x250',
        'large-rectangle' => '336x280',
        'half-page' => '300x600',
        'large-mobile-banner' => '320x100',
        #other
        'banner' => '468x60',
        'half-banner' => '234x60',
        'button' => '125x125',
        'skyscraper' => '120x600',
        'wide-skyscraper' => '160x600',
        'small-rectangle' => '180x150',
        'vertical-banner' => '120x240',
        'small-square' => '200x200',
        'portrait' => '300x1050',
        'square' => '250x250',
        'mobile-banner' => '320x50',
        'large-leaderboard' => '970x90',
        'billboard' => '970x250',
        #polish
        'single-billboard' => '750x100',
        'double-billboard' => '750x200',
        'triple-billboard' => '750x300',
    ];

    public static function getZoneTypes(): array
    {
        return array_map(function ($key, $value) {
            $sizeId = array_search($key, array_keys(self::$zoneSizes));

            $tags = ['Desktop'];
            if (strpos($key, 'mobile') !== false) {
                $tags[] = 'Mobile';
            }
            if (strpos($key, '-billboard') !== false) {
                $tags[] = 'PL';
            }
            if ($sizeId < 5) {
                $tags[] = 'best';
            }

            return [
                'id' => $sizeId + 1,
                'name' => ucwords(str_replace('-', ' ', $key)),
                'type' => $key,
                'size' => $sizeId,
                'tags' => $tags,
                'width' => explode('x', $value)[0],
                'height' => explode('x', $value)[1],
            ];
        }, array_keys(self::$zoneSizes), self::$zoneSizes);
    }

    public function pixel()
    {
        return new Response();
    }

    public function view()
    {
        return new Response();
    }

    public function click()
    {
        return new Response();
    }

    public function userData()
    {
        return new JsonResponse([
            'user' => [
                'keywords' => 'one, two, three',
                'human_score' => 5,
            ],
            'lang' => 'pl',
        ]);
    }

    public function zoneTypes()
    {
        return self::json(self::getZoneTypes(), Response::HTTP_OK);
    }

    public function targeting()
    {
        return self::json(
            json_decode(
                '[
          {
            "id": "1",
            "label": "Creative type",
            "key":"category",
            "values": [
              {"label": "Audio Ad (Auto-Play)", "value": "1"},
              {"label": "Audio Ad (User Initiated)", "value": "2"},
              {"label": "In-Banner Video Ad (Auto-Play)", "value": "6"},
              {"label": "In-Banner Video Ad (User Initiated)", "value": "7"},
              {"label": "Provocative or Suggestive Imagery", "value": "9"},
              {"label": "Shaky, Flashing, Flickering, Extreme Animation, Smileys", "value": "10"},
              {"label": "Surveys", "value": "11"},
              {"label": "Text Only", "value": "12"},
              {"label": "User Interactive (e.g., Embedded Games)", "value": "13"},
              {"label": "Windows Dialog or Alert Style", "value": "14"},
              {"label": "Has Audio On/Off Button", "value": "15"}
            ],
            "value_type": "string",
            "allow_input": true
          },
          {
            "id": "2",
            "label": "Language",
            "key": "lang",
            "values": [
              {"label": "Polish", "value": "pl"},
              {"label": "English", "value": "en"},
              {"label": "Italian", "value": "it"},
              {"label": "Japanese", "value": "jp"}
            ],
            "value_type": "string",
            "allow_input": false
          },      
          {
            "id": "3",
            "label": "Browser",
            "key": "browser",
            "values": [
              {"label": "Firefox", "value": "firefox"},
              {"label": "Chrome", "value": "chrome"},
              {"label": "Safari", "value": "safari"},
              {"label": "Edge", "value": "edge"}
            ],
            "value_type": "string",
            "allow_input": false
          },  
          {
            "id": "4",
            "label": "Javascript support",
            "key": "js_enabled",
            "value_type": "boolean",
            "values": [
              {"label": "Yes", "value": "true"},
              {"label": "No", "value": "false"}
            ],
            "allow_input": false
          }
        ]'
            ),
            200
        );
    }
}

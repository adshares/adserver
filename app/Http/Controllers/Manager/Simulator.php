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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class Simulator extends Controller
{
    /**
     * iso-639-1
     */
    public const LANGUAGES = [
        'ab' => 'Abkhazian',
        'aa' => 'Afar',
        'af' => 'Afrikaans',
        'ak' => 'Akan',
        'sq' => 'Albanian',
        'am' => 'Amharic',
        'ar' => 'Arabic',
        'an' => 'Aragonese',
        'hy' => 'Armenian',
        'as' => 'Assamese',
        'av' => 'Avaric',
        'ae' => 'Avestan',
        'ay' => 'Aymara',
        'az' => 'Azerbaijani',
        'bm' => 'Bambara',
        'ba' => 'Bashkir',
        'eu' => 'Basque',
        'be' => 'Belarusian',
        'bn' => 'Bengali',
        'bh' => 'Bihari languages',
        'bi' => 'Bislama',
        'bs' => 'Bosnian',
        'br' => 'Breton',
        'bg' => 'Bulgarian',
        'my' => 'Burmese',
        'ca' => 'Catalan, Valencian',
        'km' => 'Central Khmer',
        'ch' => 'Chamorro',
        'ce' => 'Chechen',
        'ny' => 'Chichewa, Chewa, Nyanja',
        'zh' => 'Chinese',
        'cu' => 'Church Slavonic, Old Bulgarian, Old Church Slavonic',
        'cv' => 'Chuvash',
        'kw' => 'Cornish',
        'co' => 'Corsican',
        'cr' => 'Cree',
        'hr' => 'Croatian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'dv' => 'Divehi, Dhivehi, Maldivian',
        'nl' => 'Dutch, Flemish',
        'dz' => 'Dzongkha',
        'en' => 'English',
        'eo' => 'Esperanto',
        'et' => 'Estonian',
        'ee' => 'Ewe',
        'fo' => 'Faroese',
        'fj' => 'Fijian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'ff' => 'Fulah',
        'gd' => 'Gaelic, Scottish Gaelic',
        'gl' => 'Galician',
        'lg' => 'Ganda',
        'ka' => 'Georgian',
        'de' => 'German',
        'ki' => 'Gikuyu, Kikuyu',
        'el' => 'Greek (Modern)',
        'kl' => 'Greenlandic, Kalaallisut',
        'gn' => 'Guarani',
        'gu' => 'Gujarati',
        'ht' => 'Haitian, Haitian Creole',
        'ha' => 'Hausa',
        'he' => 'Hebrew',
        'hz' => 'Herero',
        'hi' => 'Hindi',
        'ho' => 'Hiri Motu',
        'hu' => 'Hungarian',
        'is' => 'Icelandic',
        'io' => 'Ido',
        'ig' => 'Igbo',
        'id' => 'Indonesian',
        'ia' => 'Interlingua (International Auxiliary Language Association)',
        'ie' => 'Interlingue',
        'iu' => 'Inuktitut',
        'ik' => 'Inupiaq',
        'ga' => 'Irish',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'jv' => 'Javanese',
        'kn' => 'Kannada',
        'kr' => 'Kanuri',
        'ks' => 'Kashmiri',
        'kk' => 'Kazakh',
        'rw' => 'Kinyarwanda',
        'kv' => 'Komi',
        'kg' => 'Kongo',
        'ko' => 'Korean',
        'kj' => 'Kwanyama, Kuanyama',
        'ku' => 'Kurdish',
        'ky' => 'Kyrgyz',
        'lo' => 'Lao',
        'la' => 'Latin',
        'lv' => 'Latvian',
        'lb' => 'Letzeburgesch, Luxembourgish',
        'li' => 'Limburgish, Limburgan, Limburger',
        'ln' => 'Lingala',
        'lt' => 'Lithuanian',
        'lu' => 'Luba-Katanga',
        'mk' => 'Macedonian',
        'mg' => 'Malagasy',
        'ms' => 'Malay',
        'ml' => 'Malayalam',
        'mt' => 'Maltese',
        'gv' => 'Manx',
        'mi' => 'Maori',
        'mr' => 'Marathi',
        'mh' => 'Marshallese',
        'ro' => 'Moldovan, Moldavian, Romanian',
        'mn' => 'Mongolian',
        'na' => 'Nauru',
        'nv' => 'Navajo, Navaho',
        'nd' => 'Northern Ndebele',
        'ng' => 'Ndonga',
        'ne' => 'Nepali',
        'se' => 'Northern Sami',
        'no' => 'Norwegian',
        'nb' => 'Norwegian Bokmål',
        'nn' => 'Norwegian Nynorsk',
        'ii' => 'Nuosu, Sichuan Yi',
        'oc' => 'Occitan (post 1500)',
        'oj' => 'Ojibwa',
        'or' => 'Oriya',
        'om' => 'Oromo',
        'os' => 'Ossetian, Ossetic',
        'pi' => 'Pali',
        'pa' => 'Panjabi, Punjabi',
        'ps' => 'Pashto, Pushto',
        'fa' => 'Persian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'qu' => 'Quechua',
        'rm' => 'Romansh',
        'rn' => 'Rundi',
        'ru' => 'Russian',
        'sm' => 'Samoan',
        'sg' => 'Sango',
        'sa' => 'Sanskrit',
        'sc' => 'Sardinian',
        'sr' => 'Serbian',
        'sn' => 'Shona',
        'sd' => 'Sindhi',
        'si' => 'Sinhala, Sinhalese',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'so' => 'Somali',
        'st' => 'Sotho, Southern',
        'nr' => 'South Ndebele',
        'es' => 'Spanish, Castilian',
        'su' => 'Sundanese',
        'sw' => 'Swahili',
        'ss' => 'Swati',
        'sv' => 'Swedish',
        'tl' => 'Tagalog',
        'ty' => 'Tahitian',
        'tg' => 'Tajik',
        'ta' => 'Tamil',
        'tt' => 'Tatar',
        'te' => 'Telugu',
        'th' => 'Thai',
        'bo' => 'Tibetan',
        'ti' => 'Tigrinya',
        'to' => 'Tonga (Tonga Islands)',
        'ts' => 'Tsonga',
        'tn' => 'Tswana',
        'tr' => 'Turkish',
        'tk' => 'Turkmen',
        'tw' => 'Twi',
        'ug' => 'Uighur, Uyghur',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        've' => 'Venda',
        'vi' => 'Vietnamese',
        'vo' => 'Volap_k',
        'wa' => 'Walloon',
        'cy' => 'Welsh',
        'fy' => 'Western Frisian',
        'wo' => 'Wolof',
        'xh' => 'Xhosa',
        'yi' => 'Yiddish',
        'yo' => 'Yoruba',
        'za' => 'Zhuang, Chuang',
        'zu' => 'Zulu',
    ];
    public const TARGETING_JSON = <<<TARGETING_JSON
[
          {
            "label": "Site",
            "key":"site",
            "children": [
              {
                "label": "Site domain",
                "key": "domain",
                "values": [
                  {"label": "coinmarketcap.com", "value": "coinmarketcap.com"},
                  {"label": "icoalert.com", "value": "icoalert.com"}
                ],
                "value_type": "string",
                "allow_input": true
              },
              {
                "label": "Inside frame",
                "key": "inframe",
                "value_type": "boolean",
                "values": [
                  {"label": "Yes", "value": "true"},
                  {"label": "No", "value": "false"}
                ],
                "allow_input": false
              },
              {
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
                "label": "Content keywords",
                "key": "keywords",
                "values": [
                  {"label": "blockchain", "value": "blockchain"},
                  {"label": "ico", "value": "ico"}
                ],
                "value_type": "string",
                "allow_input": true
              }
            ]
          },
          {
            "label": "User",
            "key":"user",
            "children": [
              {
                "label": "Age",
                "key": "age",
                "values": [
                  {"label": "18-35", "value": "18,35"},
                  {"label": "36-65", "value": "36,65"}
                ],
                "value_type": "number",
                "allow_input": true
              },
              {

                "label": "Height",
                "key": "height",
                "values": [
                  {"label": "900 or more", "value": "<900,>"},
                  {"label": "between 200 and 300", "value": "<200,300>"}
                ],
                "value_type": "number",
                "allow_input": true
              },
              {
                "label": "Interest keywords",
                "key": "keywords",
                "values": [
                  {"label": "blockchain", "value": "blockchain"},
                  {"label": "ico", "value": "ico"}
                ],
                "value_type": "string",
                "allow_input": true
              },
              {
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
                "label": "Gender",
                "key": "gender",
                "values": [
                  {"label": "Male", "value": "pl"},
                  {"label": "Female", "value": "en"}
                ],
                "value_type": "string",
                "allow_input": false
              },
              {
                "label": "Geo",
                "key":"geo",
                "children": [
                  {
                    "label": "Continent",
                    "key": "continent",
                    "values": [
                      {"label": "Africa", "value": "af"},
                      {"label": "Asia", "value": "as"},
                      {"label": "Europe", "value": "eu"},
                      {"label": "North America", "value": "na"},
                      {"label": "South America", "value": "sa"},
                      {"label": "Oceania", "value": "oc"},
                      {"label": "Antarctica", "value": "an"}
                    ],
                    "value_type": "string",
                    "allow_input": false
                  },
                  {
                    "label": "Country",
                    "key": "country",
                    "values": [
                      {"label": "United States", "value": "us"},
                      {"label": "Poland", "value": "pl"},
                      {"label": "Spain", "value": "eu"},
                      {"label": "China", "value": "cn"}
                    ],
                    "value_type": "string",
                    "allow_input": false
                  }
                ]
              }
            ]
          },
          {
            "label": "Device",
            "key":"device",
            "children": [
              {
                "label": "Screen size",
                "key":"screen",
                "children": [
                  {
                    "label": "Width",
                    "key": "width",
                    "values": [
                      {"label": "1200 or more", "value": "<1200,>"},
                      {"label": "between 1200 and 1800", "value": "<1200,1800>"}
                    ],
                    "value_type": "number",
                    "allow_input": true
                  },
                  {
                    "label": "Height",
                    "key": "height",
                    "values": [
                      {"label": "1200 or more", "value": "<1200,>"},
                      {"label": "between 1200 and 1800", "value": "<1200,1800>"}
                    ],
                    "value_type": "number",
                    "allow_input": true
                  }
                ]
              },
              {
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
                "label": "Browser",
                "key": "browser",
                "values": [
                  {"label": "Chrome", "value": "Chrome"},
                  {"label": "Edge", "value": "Edge"},
                  {"label": "Firefox", "value": "Firefox"}
                ],
                "value_type": "string",
                "allow_input": false
              },
              {
                "label": "Operating system",
                "key": "os",
                "values": [
                  {"label": "Linux", "value": "Linux"},
                  {"label": "Mac", "value": "Mac"},
                  {"label": "Windows", "value": "Windows"}
                ],
                "value_type": "string",
                "allow_input": false
              },
              {
                "label": "Geo",
                "key":"geo",
                "children": [
                  {
                    "label": "Continent",
                    "key": "continent",
                    "values": [
                      {"label": "Africa", "value": "af"},
                      {"label": "Asia", "value": "as"},
                      {"label": "Europe", "value": "eu"},
                      {"label": "North America", "value": "na"},
                      {"label": "South America", "value": "sa"},
                      {"label": "Oceania", "value": "oc"},
                      {"label": "Antarctica", "value": "an"}
                    ],
                    "value_type": "string",
                    "allow_input": false
                  },
                  {
                    "label": "Country",
                    "key": "country",
                    "values": [
                      {"label": "United States", "value": "us"},
                      {"label": "Poland", "value": "pl"},
                      {"label": "Spain", "value": "eu"},
                      {"label": "China", "value": "cn"}
                    ],
                    "value_type": "string",
                    "allow_input": false
                  }
                ]
              },
              {
                "label": "Javascript support",
                "key": "js_enabled",
                "value_type": "boolean",
                "values": [
                  {"label": "Yes", "value": "true"},
                  {"label": "No", "value": "false"}
                ],
                "allow_input": false
              }
            ]
          }
        ]
TARGETING_JSON;
    public const FILTERING_JSON = <<<FILTERING_JSON
[
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
        ]
FILTERING_JSON;

    public static function findLabelBySize(string $size): string
    {
        return Collection::make(Zone::ZONE_LABELS)->search($size) ?: $size;
    }

    public static function getZoneTypes(): array
    {
        return array_map(
            function ($key, $value) {
                $sizeId = array_search($key, array_keys(Zone::ZONE_LABELS), false);

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
                    'label' => $key,
                    'size' => $sizeId,
                    'tags' => $tags,
                    'width' => explode('x', $value)[0],
                    'height' => explode('x', $value)[1],
                ];
            },
            array_keys(Zone::ZONE_LABELS),
            Zone::ZONE_LABELS
        );
    }

    public static function getAvailableLanguages()
    {
        return array_map(
            function ($key, $value) {
                return ['name' => $value, 'code' => $key];
            },
            array_keys(self::LANGUAGES),
            self::LANGUAGES
        );
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
        return new JsonResponse(
            [
                'user' => [
                    'keywords' => 'one, two, three',
                    'human_score' => 5,
                ],
                'lang' => 'pl',
            ]
        );
    }
}

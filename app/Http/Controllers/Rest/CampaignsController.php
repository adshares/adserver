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

namespace Adshares\Adserver\Http\Controllers\Rest;

use Adshares\Adserver\Http\Controllers\Controller;
use Adshares\Adserver\Jobs\ClassifyCampaign;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Notification;
use Adshares\Adserver\Repository\CampaignRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CampaignsController extends Controller
{
    /**
     * @var CampaignRepository
     */
    private $campaignRepository;

    public function __construct(CampaignRepository $campaignRepository)
    {
        $this->campaignRepository = $campaignRepository;
    }

    public function add(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'campaign', Campaign::$rules);
        $input = $request->input('campaign');
        $input['user_id'] = Auth::user()->id;
        $input['targeting_requires'] = $request->input('campaign.targeting.requires');
        $input['targeting_excludes'] = $request->input('campaign.targeting.excludes');

        $banners = [];
        $temporaryFileToRemove = [];
        if (isset($input['ads']) && count($input['ads']) > 0) {
            $temporaryFileToRemove = $this->temporaryBannersToRemove($input['ads']);
            $banners = $this->prepareBannersFromInput($input['ads']);
        }

        $campaign = new Campaign($input);
        $this->campaignRepository->save($campaign, $banners);

        if ($temporaryFileToRemove) {
            $this->removeLocalBannerImages($temporaryFileToRemove);
        }

        return self::json([], Response::HTTP_CREATED)
            ->header('Location', route('app.campaigns.read', ['campaign' => $campaign]));
    }

    private function temporaryBannersToRemove(array $input): array
    {
        $banners = [];

        foreach ($input as $banner) {
            if ($banner['type'] === Banner::HTML_TYPE) {
                continue;
            }

            $banners[] = $this->getBannerLocalPublicPath($banner['image_url']);
        }

        return $banners;
    }

    private function removeLocalBannerImages(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }
    }

    private function prepareBannersFromInput(array $input): array
    {
        $banners = [];

        foreach ($input as $banner) {
            $size = explode('x', Banner::size($banner['size']));

            if (!isset($size[0]) || !isset($size[1])) {
                throw new \RuntimeException('Banner size is required.');
            }

            $bannerModel = new Banner();
            $bannerModel->name = $banner['name'];
            $bannerModel->creative_width = $size[0];
            $bannerModel->creative_height = $size[1];
            $bannerModel->creative_type = Banner::type($banner['type']);

            if ($banner['type'] === Banner::HTML_TYPE) {
                $bannerModel->creative_contents = $banner['html'];
            } else {
                $path = $this->getBannerLocalPublicPath($banner['image_url']);
                $content = Storage::disk('public')->get($path);

                $bannerModel->creative_contents = $content;
            }

            $banners[] = $bannerModel;
        }

        return $banners;
    }

    private function getBannerLocalPublicPath(string $imageUrl): string
    {
        return str_replace(config('app.url') . '/storage/', '', $imageUrl);
    }

    public function browse()
    {
        $campaigns = $this->campaignRepository->find();

        return self::json($campaigns);
    }

    public function count()
    {
        //@TODO: create function data
        $siteCount = [
            'totalBudget' => 0,
            'totalClicks' => 0,
            'totalImpressions' => 0,
            'averageCTR' => 0,
            'averageCPC' => 0,
            'totalCost' => 0,
        ];

        return self::json($siteCount, 200);
    }

    public function edit(Request $request, $campaignId)
    {
        $this->validateRequestObject(
            $request,
            'campaign',
            array_intersect_key(
                Campaign::$rules,
                $request->input('campaign')
            )
        );

        // TODO check privileges
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $campaign->update($request->input('campaign'));


        return self::json(['message' => 'Successfully edited'], 200)
            ->header('Location', route('app.campaigns.read', ['campaign' => $campaign]));
    }

    public function delete($campaignId)
    {
        // TODO check privileges
        $site = $this->campaignRepository->fetchCampaignById($campaignId);
        $site->deleted_at = new \DateTime();
        $site->save();

        return self::json(['message' => 'Successfully deleted'], 200);
    }

    public function read(Request $request, $campaignId)
    {
        // TODO check privileges
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        return self::json(['campaign' => $campaign->toArray()]);
    }

    public function classify($campaignId)
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);

        $targetingRequires = ($campaign->targeting_requires) ? json_decode($campaign->targeting_requires, true) : null;
        $targetingExcludes = ($campaign->targeting_excludes) ? json_decode($campaign->targeting_excludes, true) : null;
        $banners = $campaign->getBannersUrls();

        ClassifyCampaign::dispatch($campaignId, $targetingRequires, $targetingExcludes, $banners);

        $campaign->classification_status = 1;
        $campaign->update();

        Notification::add(
            $campaign->user_id,
            Notification::CLASSIFICATION_TYPE,
            'Classify queued',
            sprintf('Campaign %s has been queued to classify', $campaign->id)
        );

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function disableClassify($campaignId)
    {
        $campaign = $this->campaignRepository->fetchCampaignById($campaignId);
        $campaign->classification_status = 0;
        $campaign->classification_tags = null;

        $campaign->update();
    }

    public function upload(Request $request)
    {
        $file = $request->file('file');
        $path = $file->store('banners', 'public');

        $name = $file->getClientOriginalName();
        $imageSize = getimagesize($file->getRealPath());
        $size = '';

        if (isset($imageSize[0]) && isset($imageSize[1])) {
            $size = sprintf('%sx%s', $imageSize[0], $imageSize[1]);
        }

        return self::json(
            [
                'imageUrl' => config('app.url') . '/storage/' . $path,
                'name' => $name,
                'size' => $size,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Adshares\Adserver\Exceptions\JsonResponseException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function targeting(Request $request)
    {
        return self::json(
            json_decode(
                <<<'JSON'
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
JSON
            ),
            200
        );
    }
}

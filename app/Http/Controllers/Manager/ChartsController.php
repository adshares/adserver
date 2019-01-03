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
use Illuminate\Http\JsonResponse;

class ChartsController extends Controller
{
    public function chart(): JsonResponse
    {
        return self::json(
            json_decode(
                '{
        "values": [
          96,
          66,
          82,
          76,
          73,
          62,
          78,
          34,
          38,
          78,
          65,
          66,
          85,
          39,
          93,
          31,
          60,
          38,
          87,
          21,
          76,
          74,
          72,
          54,
          35,
          58,
          96,
          90
        ],
        "timestamps": [
          "2018-12-01T16:58:14+00:00",
          "2018-12-02T16:58:14+00:00",
          "2018-12-03T16:58:14+00:00",
          "2018-12-04T16:58:14+00:00",
          "2018-12-05T16:58:14+00:00",
          "2018-12-06T16:58:14+00:00",
          "2018-12-07T16:58:14+00:00",
          "2018-12-08T16:58:14+00:00",
          "2018-12-09T16:58:14+00:00",
          "2018-12-10T16:58:14+00:00",
          "2018-12-11T16:58:14+00:00",
          "2018-12-12T16:58:14+00:00",
          "2018-12-13T16:58:14+00:00",
          "2018-12-14T16:58:14+00:00",
          "2018-12-15T16:58:14+00:00",
          "2018-12-16T16:58:14+00:00",
          "2018-12-17T16:58:14+00:00",
          "2018-12-18T16:58:14+00:00",
          "2018-12-19T16:58:14+00:00",
          "2018-12-20T16:58:14+00:00",
          "2018-12-21T16:58:14+00:00",
          "2018-12-22T16:58:14+00:00",
          "2018-12-23T16:58:14+00:00",
          "2018-12-24T16:58:14+00:00",
          "2018-12-25T16:58:14+00:00",
          "2018-12-26T16:58:14+00:00",
          "2018-12-27T16:58:14+00:00",
          "2018-12-28T16:58:14+00:00"
        ],
        "total": 5200,
        "difference": 300,
        "differenceInPercentage": 0.06
      }'
            )
        );
    }

    public function publisherChart(): JsonResponse
    {
        return self::json(
            json_decode(
                '[{
      "values": [
        96,
        66,
        82,
        76,
        73,
        62,
        78,
        34,
        38,
        78,
        65,
        66,
        85,
        39,
        93,
        31,
        60,
        38,
        87,
        21,
        76,
        74,
        72,
        54,
        35,
        58,
        96,
        90
      ],
      "timestamps": [
          "2018-12-01T16:58:14+00:00",
          "2018-12-02T16:58:14+00:00",
          "2018-12-03T16:58:14+00:00",
          "2018-12-04T16:58:14+00:00",
          "2018-12-05T16:58:14+00:00",
          "2018-12-06T16:58:14+00:00",
          "2018-12-07T16:58:14+00:00",
          "2018-12-08T16:58:14+00:00",
          "2018-12-09T16:58:14+00:00",
          "2018-12-10T16:58:14+00:00",
          "2018-12-11T16:58:14+00:00",
          "2018-12-12T16:58:14+00:00",
          "2018-12-13T16:58:14+00:00",
          "2018-12-14T16:58:14+00:00",
          "2018-12-15T16:58:14+00:00",
          "2018-12-16T16:58:14+00:00",
          "2018-12-17T16:58:14+00:00",
          "2018-12-18T16:58:14+00:00",
          "2018-12-19T16:58:14+00:00",
          "2018-12-20T16:58:14+00:00",
          "2018-12-21T16:58:14+00:00",
          "2018-12-22T16:58:14+00:00",
          "2018-12-23T16:58:14+00:00",
          "2018-12-24T16:58:14+00:00",
          "2018-12-25T16:58:14+00:00",
          "2018-12-26T16:58:14+00:00",
          "2018-12-27T16:58:14+00:00",
          "2018-12-28T16:58:14+00:00"
      ],
      "total": 5200,
      "difference": 300,
      "differenceInPercentage": 0.06
    }]'
            )
        );
    }
}

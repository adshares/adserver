<?php

namespace Adshares\Adserver\Http\Controllers\App;

class ChartsController extends AppController
{
    public function chart()
    {
        return self::json(json_decode('{
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
          "Thu Feb 01 2018 15:05:18 GMT+0100 (CET)",
          "Fri Feb 02 2018 15:05:18 GMT+0100 (CET)",
          "Sat Feb 03 2018 15:05:18 GMT+0100 (CET)",
          "Sun Feb 04 2018 15:05:18 GMT+0100 (CET)",
          "Mon Feb 05 2018 15:05:18 GMT+0100 (CET)",
          "Tue Feb 06 2018 15:05:18 GMT+0100 (CET)",
          "Wed Feb 07 2018 15:05:18 GMT+0100 (CET)",
          "Thu Feb 08 2018 15:05:18 GMT+0100 (CET)",
          "Fri Feb 09 2018 15:05:18 GMT+0100 (CET)",
          "Sat Feb 10 2018 15:05:18 GMT+0100 (CET)",
          "Sun Feb 11 2018 15:05:18 GMT+0100 (CET)",
          "Mon Feb 12 2018 15:05:18 GMT+0100 (CET)",
          "Tue Feb 13 2018 15:05:18 GMT+0100 (CET)",
          "Wed Feb 14 2018 15:05:18 GMT+0100 (CET)",
          "Thu Feb 15 2018 15:05:18 GMT+0100 (CET)",
          "Fri Feb 16 2018 15:05:18 GMT+0100 (CET)",
          "Sat Feb 17 2018 15:05:18 GMT+0100 (CET)",
          "Sun Feb 18 2018 15:05:18 GMT+0100 (CET)",
          "Mon Feb 19 2018 15:05:18 GMT+0100 (CET)",
          "Tue Feb 20 2018 15:05:18 GMT+0100 (CET)",
          "Wed Feb 21 2018 15:05:18 GMT+0100 (CET)",
          "Thu Feb 22 2018 15:05:18 GMT+0100 (CET)",
          "Fri Feb 23 2018 15:05:18 GMT+0100 (CET)",
          "Sat Feb 24 2018 15:05:18 GMT+0100 (CET)",
          "Sun Feb 25 2018 15:05:18 GMT+0100 (CET)",
          "Mon Feb 26 2018 15:05:18 GMT+0100 (CET)",
          "Tue Feb 27 2018 15:05:18 GMT+0100 (CET)",
          "Wed Feb 28 2018 15:05:18 GMT+0100 (CET)"
        ],
        "total": 5200,
        "difference": 300,
        "differenceInPercentage": 0.06
      }'));
    }

    public function publisherChart()
    {
        return self::json(json_decode('[{
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
        "Thu Feb 01 2018 15:05:18 GMT+0100 (CET)",
        "Fri Feb 02 2018 15:05:18 GMT+0100 (CET)",
        "Sat Feb 03 2018 15:05:18 GMT+0100 (CET)",
        "Sun Feb 04 2018 15:05:18 GMT+0100 (CET)",
        "Mon Feb 05 2018 15:05:18 GMT+0100 (CET)",
        "Tue Feb 06 2018 15:05:18 GMT+0100 (CET)",
        "Wed Feb 07 2018 15:05:18 GMT+0100 (CET)",
        "Thu Feb 08 2018 15:05:18 GMT+0100 (CET)",
        "Fri Feb 09 2018 15:05:18 GMT+0100 (CET)",
        "Sat Feb 10 2018 15:05:18 GMT+0100 (CET)",
        "Sun Feb 11 2018 15:05:18 GMT+0100 (CET)",
        "Mon Feb 12 2018 15:05:18 GMT+0100 (CET)",
        "Tue Feb 13 2018 15:05:18 GMT+0100 (CET)",
        "Wed Feb 14 2018 15:05:18 GMT+0100 (CET)",
        "Thu Feb 15 2018 15:05:18 GMT+0100 (CET)",
        "Fri Feb 16 2018 15:05:18 GMT+0100 (CET)",
        "Sat Feb 17 2018 15:05:18 GMT+0100 (CET)",
        "Sun Feb 18 2018 15:05:18 GMT+0100 (CET)",
        "Mon Feb 19 2018 15:05:18 GMT+0100 (CET)",
        "Tue Feb 20 2018 15:05:18 GMT+0100 (CET)",
        "Wed Feb 21 2018 15:05:18 GMT+0100 (CET)",
        "Thu Feb 22 2018 15:05:18 GMT+0100 (CET)",
        "Fri Feb 23 2018 15:05:18 GMT+0100 (CET)",
        "Sat Feb 24 2018 15:05:18 GMT+0100 (CET)",
        "Sun Feb 25 2018 15:05:18 GMT+0100 (CET)",
        "Mon Feb 26 2018 15:05:18 GMT+0100 (CET)",
        "Tue Feb 27 2018 15:05:18 GMT+0100 (CET)",
        "Wed Feb 28 2018 15:05:18 GMT+0100 (CET)"
      ],
      "total": 5200,
      "difference": 300,
      "differenceInPercentage": 0.06
    }]'));
    }
}

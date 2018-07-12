<?php

namespace Adshares\Adserver\Http\Controllers\App;

class AdserverController extends AppController
{
    /**
     * Return adserver adshares address.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function depositAddress()
    {
        return self::json(['adsharesAddress' => config('app.adshares_address')], 200);
    }

    /**
     * Return adserver users notifications.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function notifications()
    {
        return self::json(json_decode('[
          {
            "id": 8897312,
            "notificationType": 0,
            "userType": 0,
            "title": "This is the title of a notification",
            "message": "We have a new feedback for your campaign",
            "availableActions": [0, 1],
            "assetId": 183297987312
          },
          {
            "id": 32879132312,
            "notificationType": 2,
            "userType": 0,
            "title": "This is the title of a notification",
            "message": "Your campaign changed status for \'Inactive\'",
            "availableActions": [2]
          },
          {
            "id": 879179312,
            "notificationType": 4,
            "userType": 1,
            "message": "We have a new feedback for your campaign",
            "availableActions": [0, 1]
          },
          {
            "id": 913287897312,
            "notificationType": 3,
            "userType": 1,
            "message": "Your site generated 100ADS of revenue yesterday",
            "availableActions": [0, 1]
          },
          {
            "id": 87232897312,
            "notificationType": 2,
            "userType": 1,
            "title": "This is the title of a notification",
            "message": "We have a new feedback for your site",
            "availableActions": [2]
          },
          {
            "id": 132897312,
            "notificationType": 1,
            "userType": 2,
            "message": "Set up your wallet",
            "availableActions": [2]
          },
          {
            "id": 9132897312,
            "notificationType": 1,
            "userType": 2,
            "message": "Your account was created",
            "availableActions": [2, 3]
          }
        ]'), 200);
    }
}

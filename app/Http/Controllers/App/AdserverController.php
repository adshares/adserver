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
}

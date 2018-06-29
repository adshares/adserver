<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Illuminate\Http\Request;
use Adshares\Adserver\Models\Site;

class SitesController extends AppController
{
    public function add(Request $request)
    {
        $this->validateRequest($request, 'site', Site::$rules);
        $site = Site::create($request->input('site'));
        $site->save();

        $response = self::json(compact('site'), 201);
        $response->header('Location', route('app.sites.read', ['site' => $site]));

        return $response;
    }

    public function browse(Request $request)
    {
        // TODO check privileges
        $sites = Site::whereNull('deleted_at')->get();

        return self::json($sites);
    }

    public function edit(Request $request, $siteId)
    {
    }

    public function delete(Request $request, $siteId)
    {
        // TODO check privileges
        $site = Site::whereNull('deleted_at')->findOrFail($siteId);
        $site->deleted_at = new \DateTime();
        $site->Site();

        return self::json(['message' => 'Successful deleted'], 200);
    }

    public function read(Request $request, $siteId)
    {
        // TODO check privileges
        $site = Site::whereNull('deleted_at')->findOrFail($siteId);

        return self::json(compact('site'));
    }
}

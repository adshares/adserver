<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Exceptions\JsonResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class AppController extends BaseController
{
    /**
     * @param array $data
     * @param int $code
     * @param mixed $errors
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function json($data = [], $code = 200, $errors = false)
    {
        if (empty($errors)) {
            return Response::json($data, $code);
        }
        $data['errors'] = $errors;

        return Response::json($data, $code);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $name
     * @param array $rules
     *
     * @return array
     *
     * @throws JsonResponseException
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequestObject(Request $request, String $name, array $rules)
    {
        if (!$request->has($name)) {
            throw new JsonResponseException(self::json([], 422, ['message' => "Missing request object '$name'"]));
        }
        /* @var $validator \Illuminate\Validation\Validator */
        $validator = Validator::make($request->input($name), $rules);

        return $validator->validate();
    }

    public function mockWalletHistory(Request $request)
    {
        return file_get_contents(base_path('mocks/wallet-history_get.json'));
    }

    public function mock(Request $request)
    {
        $pathInfo = str_replace(['/panel', '/app', '/api'], ['', '', ''], $request->getPathInfo());
        $path = substr(str_replace('/', '_', $pathInfo), 1);
        $method = strtolower($request->method());

        $filePath = "mocks/mappings/{$path}_{$method}.json";

        if (!is_file(base_path($filePath))) {
            $filePath = "mocks/mappings/{$path}__{$method}.json";
        }

        try {
            $json = file_get_contents(base_path($filePath));
        } catch (\ErrorException $errorException) {
            abort(404, $errorException->getMessage());
        }

        try {
            $data = \GuzzleHttp\json_decode($json, true);

            return self::json($data['response']['jsonBody']);
        } catch (\Exception $errorException) {
            return self::json([], 500, [$errorException->getMessage()]);
        }
    }
}

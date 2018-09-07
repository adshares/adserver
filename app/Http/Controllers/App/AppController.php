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
}

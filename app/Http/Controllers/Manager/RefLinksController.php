<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\RefLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RefLinksController extends Controller
{
    public function info(string $token): JsonResponse
    {
        if (null === ($refLink = RefLink::fetchByToken($token, true))) {
            throw new NotFoundHttpException(sprintf('No ref link found for token: %s', $token));
        }
        return self::json([
            'token' => $refLink->token,
            'status' => $refLink->status,
        ]);
    }

    public function browse(): JsonResponse
    {
        return self::json(
            RefLink::fetchByUser(Auth::user()->id)
                ->sortByDesc('id')
                ->values()
                ->toArray()
        );
    }

    public function add(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (null === ($input = $request->input('ref_link'))) {
            $input = [];
        }

        if (!$user->isAdmin() && !empty(array_diff(array_keys($input), ['token', 'comment', 'kept_refund']))) {
            throw new UnprocessableEntityHttpException('Insufficient permissions');
        }

        $input['user_id'] = $user->id;
        if (empty($input['token'])) {
            $input['token'] = RefLink::generateToken();
        }

        Validator::make($input, RefLink::$rules)->validate();
        $refLink = RefLink::create($input);

        return self::json($refLink, Response::HTTP_CREATED);
    }
}

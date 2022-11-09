<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\User;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OAuthController extends Controller
{
    public function login(Request $request): Response
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw new UnprocessableEntityHttpException('Invalid credentials');
        }
        /** @var User $user */
        $user = Auth::user();
        if ($user->isBanned()) {
            return new JsonResponse(['reason' => $user->ban_reason], Response::HTTP_FORBIDDEN);
        }

        $request->session()->regenerate();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function walletLogin(Request $request): JsonResponse
    {
        try {
            $address = new WalletAddress($request->input('network'), $request->input('address'));
        } catch (InvalidArgumentException) {
            throw new UnprocessableEntityHttpException('Invalid wallet address');
        }
        if (null === ($user = User::fetchByWalletAddress($address))) {
            throw new UnprocessableEntityHttpException('Incorrect wallet address');
        }
        if ($user->isBanned()) {
            return new JsonResponse(['reason' => $user->ban_reason], Response::HTTP_FORBIDDEN);
        }

        $credentials = $request->only('token', 'signature');
        $credentials['wallet_address'] = $address;
        if (!Auth::guard()->attempt($credentials)) {
            throw new UnprocessableEntityHttpException('Invalid signature');
        }

        $request->session()->regenerate();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

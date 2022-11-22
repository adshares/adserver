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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtController extends Controller
{
    public function __construct()
    {
        auth()->setDefaultDriver('jwt');
        $this->middleware('auth:jwt', ['except' => ['login']]);
    }

    public function login(Request $request): Response
    {
        $credentials = $request->only(['email', 'password']);

        if (!$token = auth()->attempt($credentials)) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        $this->updateUserActivity();

        return $this->json(['token' => $token]);
    }

    public function logout(): Response
    {
        $this->updateUserActivity();
        auth()->logout();

        return $this->json(['message' => 'Successfully logged out']);
    }

    public function refresh(): Response
    {
        $this->updateUserActivity();
        return $this->json(['token' => auth()->refresh()]);
    }

    private function updateUserActivity(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $user->last_active_at = now();
        $user->save();
    }
}

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\User as UserBridge;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

class OAuthController extends Controller
{
    use HandlesOAuthErrors;

    public function __construct(private readonly AuthorizationServer $server)
    {
    }

    public function authorizeUser(
        ServerRequestInterface $psrRequest,
        Request $request,
    ): JsonResponse {
        $noRedirect = null !== $request->query('no_redirect');

        /** @var User $user */
        $user = Auth::user();
        if ($user->isBanned()) {
            return new JsonResponse(['reason' => $user->ban_reason], Response::HTTP_FORBIDDEN);
        }

        $authRequest = $this->withErrorHandling(function () use ($psrRequest) {
            return $this->server->validateAuthorizationRequest($psrRequest);
        });

        $request->session()->forget('promptedForLogin');

        return $this->approveRequest($authRequest, $user, $noRedirect);
    }

    protected function approveRequest(AuthorizationRequest $authRequest, Model $user, bool $noRedirect): JsonResponse
    {
        $authRequest->setUser(new UserBridge($user->getAuthIdentifier()));
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(function () use ($authRequest, $noRedirect) {
            $psrResponse = $this->server->completeAuthorizationRequest($authRequest, new Psr7Response());
            $headers = $psrResponse->getHeaders();

            if ($noRedirect && Response::HTTP_FOUND === $psrResponse->getStatusCode()) {
                return new JsonResponse(
                    ['location' => $headers['Location'][0]],
                    Response::HTTP_OK,
                    $headers
                );
            } else {
                return new JsonResponse(
                    $psrResponse->getBody(),
                    $psrResponse->getStatusCode(),
                    $headers
                );
            }
        });
    }
}

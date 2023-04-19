<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http;

use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Utilities\EthUtils;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    protected static function json($data = [], $code = 200, $errors = false): JsonResponse
    {
        if (empty($errors)) {
            return Response::json($data, $code);
        }
        $data['errors'] = $errors;

        return Response::json($data, $code);
    }

    /**
     * @deprecated
     */
    protected function validateRequestObject(Request $request, string $name, array $rules)
    {
        if (!$request->has($name)) {
            throw new UnprocessableEntityHttpException("Missing request object '$name'");
        }

        $validator = Validator::make($request->input($name), $rules);

        return $validator->validate();
    }

    protected function checkWalletAddress(?string $token, string $type, array $data, ?int $userId = null): WalletAddress
    {
        if (false === ($walletToken = Token::check($token, $userId, $type))) {
            throw new UnprocessableEntityHttpException('Invalid token');
        }

        try {
            $address = new WalletAddress($data['network'] ?? '', $data['address'] ?? '');
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException('Invalid wallet address');
        }

        switch ($address->getNetwork()) {
            case WalletAddress::NETWORK_ADS:
                $adsClient = resolve(Ads::class);
                $valid = $adsClient->verifyMessage(
                    $data['signature'] ?? '',
                    $walletToken['payload']['message'],
                    $address->getAddress()
                );
                break;
            case WalletAddress::NETWORK_BSC:
                $valid = EthUtils::verifyMessage(
                    $data['signature'] ?? '',
                    $walletToken['payload']['message'],
                    $address->getAddress()
                );
                break;
            default:
                throw new UnprocessableEntityHttpException('Unsupported wallet network');
        }

        if (!$valid) {
            throw new UnprocessableEntityHttpException('Invalid signature');
        }

        return $address;
    }
}

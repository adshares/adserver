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

use Adshares\Ads\Util\AdsConverter;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Mail\Newsletter;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserSettings;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function hash_equals;
use function is_bool;

class SettingsController extends Controller
{
    /**
     * Return adserver users notifications.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function readNotifications(): JsonResponse
    {
        $settings =
            UserSettings::where('user_id', Auth::user()->id)->where('type', 'notifications')->first()->toArray();

        $payload = [];
        foreach ($settings['payload'] as $name => $value) {
            $value['name'] = $name;
            $payload[] = $value;
        }
        $settings['payload'] = $payload;

        return self::json($settings);
    }

    public function newsletterSubscription(Request $request): JsonResponse
    {
        $isSubscribed = $request->get('is_subscribed');

        if (!is_bool($isSubscribed)) {
            throw new UnprocessableEntityHttpException();
        }

        /** @var User $user */
        $user = Auth::user();
        $user->subscription($isSubscribed);
        $user->save();

        return self::json(['is_subscribed' => $isSubscribed]);
    }

    public function autoWithdrawal(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (null === $user->wallet_address) {
            return self::json(
                ['message' => 'Account is not connected with a wallet'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        if (!in_array($user->wallet_address->getNetwork(), [WalletAddress::NETWORK_ADS, WalletAddress::NETWORK_BSC])) {
            return self::json(
                ['message' => 'Unsupported wallet network'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $autoWithdrawal = $request->get('auto_withdrawal');
        if (null !== $autoWithdrawal && !ctype_digit((string)$autoWithdrawal)) {
            return self::json(
                ['message' => 'Auto withdrawal threshold must be numeric or null'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (null !== $autoWithdrawal) {
            $autoWithdrawal = (int)$autoWithdrawal;
            $min = config('app.auto_withdrawal_limit_' . strtolower($user->wallet_address->getNetwork()));
            if ($autoWithdrawal < $min) {
                return self::json(
                    [
                        'message' => sprintf(
                            'Auto withdrawal threshold must be greater than or equal to %.3f',
                            AdsConverter::clicksToAds($min)
                        )
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $user->auto_withdrawal = $autoWithdrawal;
        $user->saveOrFail();

        return self::json($user->toArray());
    }

    public function newsletterUnsubscribe(Request $request): Response
    {
        $address = (string)$request->get('address');

        if (null === ($user = User::fetchByEmail($address))) {
            Log::info('Newsletter unsubscribe failed: Invalid address');

            return response()->view(
                'common.newsletter-unsubscribe',
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $digestExpected = Newsletter::createDigest($address);
        $digest = (string)$request->get('digest');

        if (!hash_equals($digestExpected, $digest)) {
            Log::info('Newsletter unsubscribe failed: Invalid digest');

            return response()->view(
                'common.newsletter-unsubscribe',
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user->subscription(false);
        $user->save();

        return response()->view('common.newsletter-unsubscribe', ['success' => true]);
    }
}

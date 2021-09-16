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
use Adshares\Adserver\Mail\AuthRecovery;
use Adshares\Adserver\Mail\Crm\UserRegistered;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Mail\UserEmailChangeConfirm1Old;
use Adshares\Adserver\Mail\UserEmailChangeConfirm2New;
use Adshares\Adserver\Mail\UserConfirmed;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Config\RegistrationMode;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthController extends Controller
{
    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    public function __construct(ExchangeRateReader $exchangeRateReader)
    {
        $this->exchangeRateReader = $exchangeRateReader;
    }

    public function register(Request $request): JsonResponse
    {
        $registrationMode = Config::fetchStringOrFail(Config::REGISTRATION_MODE);
        if (RegistrationMode::PRIVATE === $registrationMode) {
            throw new AccessDeniedHttpException('Private registration enabled');
        }

        $data = $request->input('user');
        $refLink = null;
        if (isset($data['referral_token'])) {
            $refLink = RefLink::fetchByToken($data['referral_token']);
        }

        if (RegistrationMode::RESTRICTED === $registrationMode && null === $refLink) {
            throw new AccessDeniedHttpException('Restricted registration enabled');
        }

        $this->validateRequestObject($request, 'user', User::$rules_add);
        Validator::make($request->all(), ['uri' => 'required'])->validate();

        DB::beginTransaction();

        $user = User::register($data, $refLink);
        $token = Token::generate(Token::EMAIL_ACTIVATE, $user);
        $mailable = new UserEmailActivate($token->uuid, $request->input('uri'));

        Mail::to($user)->queue($mailable);

        DB::commit();

        return self::json([], Response::HTTP_CREATED);
    }

    public function emailActivate(Request $request): JsonResponse
    {
        Validator::make($request->all(), ['user.email_confirm_token' => 'required'])->validate();

        DB::beginTransaction();
        $token = Token::check($request->input('user.email_confirm_token'));
        if (false === $token) {
            DB::rollBack();
            return self::json([], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = User::find($token['user_id']);
        if (empty($user)) {
            DB::rollBack();
            return self::json([], Response::HTTP_FORBIDDEN);
        }

        $this->confirmEmail($user);
        if (Config::isTrueOnly(Config::AUTO_CONFIRMATION_ENABLED)) {
            $this->confirmAdmin($user);
        }
        $user->save();
        DB::commit();

        $this->sendCrmMailOnUserRegistered($user);

        return self::json($user->toArray());
    }


    public function confirm(int $userId): JsonResponse
    {
        /** @var User $user */
        $user = User::find($userId);
        if (empty($user)) {
            return self::json([], Response::HTTP_NOT_FOUND);
        }

        DB::beginTransaction();
        $this->confirmAdmin($user);
        $user->save();
        DB::commit();

        if ($user->is_confirmed) {
            Mail::to($user)->queue(new UserConfirmed());
        }

        return self::json($user->toArray());
    }

    public function emailActivateResend(Request $request): JsonResponse
    {
        Validator::make($request->all(), ['uri' => 'required'])->validate();

        /** @var User $user */
        $user = Auth::user();

        DB::beginTransaction();

        if (!Token::canGenerateToken($user, Token::EMAIL_ACTIVATE)) {
            return self::json(
                [],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'message' => 'You can request to resend email activation every 15 minutes.'
                        . ' Please wait 15 minutes or less.',
                ]
            );
        }

        $token = Token::generate(Token::EMAIL_ACTIVATE, $user);
        $mailable = new UserEmailActivate($token->uuid, $request->input('uri'));

        Mail::to($user)->queue($mailable);

        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function emailChangeStep1(Request $request): JsonResponse
    {
        Validator::make(
            $request->all(),
            ['email' => 'required|email', 'uri_step1' => 'required', 'uri_step2' => 'required']
        )->validate();
        if (User::withTrashed()->where('email', $request->input('email'))->count()) {
            return self::json(
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['email' => 'This email already exists in our database']
            );
        }

        /** @var User $user */
        $user = Auth::user();

        DB::beginTransaction();

        if (!Token::canGenerateToken($user, Token::EMAIL_CHANGE_STEP_1)) {
            return self::json(
                [],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'message' => "You have already requested email change.\n"
                        . "You can request email change every 5 minutes.\n"
                        . 'Please wait 5 minutes or less to start configuring another email address.',
                ]
            );
        }

        $token = Token::generate(Token::EMAIL_CHANGE_STEP_1, $user, $request->all());
        $mailable = new UserEmailChangeConfirm1Old($token->uuid, $request->input('uri_step1'));

        Mail::to($user)->queue($mailable);

        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function emailChangeStep2($token): JsonResponse
    {
        DB::beginTransaction();
        if (false === $token = Token::check($token)) {
            DB::commit();

            return self::json([], Response::HTTP_FORBIDDEN, ['message' => 'Invalid or outdated token']);
        }
        $user = User::findOrFail($token['user_id']);
        if (User::withTrashed()->where('email', $token['payload']['email'])->count()) {
            DB::commit();

            return self::json(
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['message' => 'This email already exists in our database']
            );
        }

        $token2 = Token::generate(Token::EMAIL_CHANGE_STEP_2, $user, $token['payload']);
        $mailable = new UserEmailChangeConfirm2New($token2->uuid, $token['payload']['uri_step2']);

        Mail::to($token['payload']['email'])->queue($mailable);

        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function emailChangeStep3($token): JsonResponse
    {
        DB::beginTransaction();
        if (false === $token = Token::check($token)) {
            DB::commit();

            return self::json([], Response::HTTP_FORBIDDEN, ['message' => 'Invalid or outdated token']);
        }
        $user = User::findOrFail($token['user_id']);
        if (User::withTrashed()->where('email', $token['payload']['email'])->count()) {
            DB::commit();

            return self::json(
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['message' => 'This email already exists in our database']
            );
        }
        $user->email = $token['payload']['email'];
        $user->email_confirmed_at = date('Y-m-d H:i:s');
        $user->save();
        DB::commit();

        return self::json($user->toArray());
    }

    public function check(): JsonResponse
    {
        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate()->toArray();
        } catch (ExchangeRateNotAvailableException $exception) {
            Log::error(sprintf('[AuthController] Cannot fetch exchange rate: %s', $exception->getMessage()));
            $exchangeRate = null;
        }

        /** @var User $user */
        $user = Auth::user();

        return self::json(
            array_merge(
                $user->toArray(),
                [
                    'exchange_rate' => $exchangeRate,
                    'referral_refund_enabled' => Config::isTrueOnly(Config::REFERRAL_REFUND_ENABLED),
                    'referral_refund_commission' => Config::fetchFloatOrFail(Config::REFERRAL_REFUND_COMMISSION),
                ]
            )
        );
    }

    public function impersonate(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return response()->json([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $token = Token::impersonate(Auth::user(), $user);

        return self::json($token->uuid);
    }

    public function login(Request $request): JsonResponse
    {
        if (
            Auth::guard()->attempt(
                $request->only('email', 'password'),
                $request->filled('remember')
            )
        ) {
            Auth::user()->generateApiKey();

            return $this->check();
        }

        return response()->json([], Response::HTTP_BAD_REQUEST);
    }

    public function logout(): JsonResponse
    {
        Auth::user()->clearApiKey();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function recovery(Request $request): JsonResponse
    {
        Validator::make($request->all(), ['email' => 'required|email', 'uri' => 'required'])->validate();

        $user = User::where('email', $request->input('email'))->first();

        if (empty($user)) {
            return self::json([], Response::HTTP_NO_CONTENT);
        }

        DB::beginTransaction();

        if (!Token::canGenerateToken($user, Token::PASSWORD_RECOVERY)) {
            return self::json([], Response::HTTP_NO_CONTENT);
        }

        $mailable = new AuthRecovery(
            Token::generate(Token::PASSWORD_RECOVERY, $user)->uuid,
            $request->input('uri')
        );

        Mail::to($user)->queue($mailable);

        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function recoveryTokenExtend($token): JsonResponse
    {
        if (!Token::extend(Token::PASSWORD_RECOVERY, $token)) {
            return self::json(
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['message' => 'Password recovery token is invalid']
            );
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function updateSelf(Request $request): JsonResponse
    {
        if (!Auth::check() && !$request->has('user.token')) {
            return self::json(
                [],
                Response::HTTP_UNAUTHORIZED,
                ['message' => 'Required authenticated access or token authentication']
            );
        }

        DB::beginTransaction();
        if (Auth::check()) {
            $user = Auth::user();
            $token_authorization = false;
        } else {
            if (false === $token = Token::check($request->input('user.token'), null, 'password-recovery')) {
                DB::rollBack();

                return self::json(
                    [],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['message' => 'Authentication token is invalid']
                );
            }
            $user = User::findOrFail($token['user_id']);
            $token_authorization = true;
        }

        $this->validateRequestObject($request, 'user', User::$rules);
        $user->fill($request->input('user'));

        if (!$request->has('user.password_new')) {
            $user->save();
            DB::commit();

            return self::json($user->toArray());
        }

        if ($token_authorization) {
            $user->password = $request->input('user.password_new');
            $user->save();
            DB::commit();

            return self::json($user->toArray());
        }

        if (!$request->has('user.password_old') || !$user->validPassword($request->input('user.password_old'))) {
            DB::rollBack();

            return self::json(
                $user->toArray(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['password_old' => 'Old password is not valid']
            );
        }

        $user->password = $request->input('user.password_new');
        $user->save();
        DB::commit();

        return self::json($user->toArray());
    }

    private function confirmEmail(User $user): void
    {
        $user->confirmEmail();
        if ($user->is_confirmed) {
            $this->awardBonus($user);
        }
    }

    private function confirmAdmin(User $user): void
    {
        $user->confirmAdmin();
        if ($user->is_confirmed) {
            $this->awardBonus($user);
        }
    }

    private function awardBonus(User $user): void
    {
        if (null !== $user->refLink && null !== $user->refLink->bonus && $user->refLink->bonus > 0) {
            try {
                $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();
                $user->awardBonus($exchangeRate->toClick($user->refLink->bonus), $user->refLink);
            } catch (ExchangeRateNotAvailableException $exception) {
                Log::error(sprintf('[AuthController] Cannot fetch exchange rate: %s', $exception->getMessage()));
            }
        }
    }

    private function sendCrmMailOnUserRegistered(User $user): void
    {
        if (config('app.crm_mail_address_on_user_registered')) {
            Mail::to(config('app.crm_mail_address_on_user_registered'))->queue(
                new UserRegistered(
                    $user->uuid,
                    $user->email,
                    ($user->created_at ?: new DateTime())->format('d/m/Y'),
                    optional($user->refLink)->token
                )
            );
        }
    }
}

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
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Filter\FilterType;
use Adshares\Adserver\Http\Requests\Order\OrderByCollection;
use Adshares\Adserver\Http\Resources\HostCollection;
use Adshares\Adserver\Http\Resources\UserCollection;
use Adshares\Adserver\Http\Resources\UserResource;
use Adshares\Adserver\Mail\AuthRecovery;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Repository\Common\ServerEventLogRepository;
use Adshares\Adserver\Repository\Common\UserRepository;
use Adshares\Adserver\ViewModel\Role;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class ServerMonitoringController extends Controller
{
    private const ADPANEL_EMAIL_ACTIVATION_URI = '/auth/email-activation/';
    private const ADPANEL_RESET_PASSWORD_URI = '/auth/reset-password/';

    public function fetchEvents(Request $request, ServerEventLogRepository $repository): array
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'createdAt' => FilterType::Date,
            'type' => FilterType::String,
        ]);
        self::validateLimit($limit);
        self::validateEventFilters($filters);

        return $repository->fetchServerEvents($filters, $limit)
            ->toArray();
    }

    public function fetchHosts(Request $request): JsonResource
    {
        $limit = $request->query('limit', 10);
        self::validateLimit($limit);

        $paginator = NetworkHost::orderBy('id')
            ->tokenPaginate($limit)
            ->withQueryString();

        return new HostCollection($paginator);
    }

    public function fetchLatestEvents(Request $request, ServerEventLogRepository $repository): array
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'type' => FilterType::String,
        ]);
        self::validateLimit($limit);
        self::validateEventFilters($filters);

        return $repository->fetchLatestServerEvents($filters, $limit)
            ->toArray();
    }

    public function fetchUsers(Request $request, UserRepository $userRepository): JsonResource
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'adminConfirmed' => FilterType::Bool,
            'emailConfirmed' => FilterType::Bool,
            'query' => FilterType::String,
            'role' => FilterType::String,
        ]);
        $orderBy = OrderByCollection::fromRequest($request);
        self::validateLimit($limit);
        self::validateUserFilters($filters);
        self::validateUserOrderBy($orderBy);

        return new UserCollection($userRepository->fetchUsers($filters, $orderBy, $limit));
    }

    public function fetchWallet(): JsonResponse
    {
        return self::json([
            'wallet' => [
                'balance' => UserLedgerEntry::getBalanceForAllUsers(),
                'unusedBonuses' => UserLedgerEntry::getBonusBalanceForAllUsers(),
            ]
        ]);
    }

    public function banUser(AdminController $adminController, Request $request, int $userId): JsonResource
    {
        $adminController->banUser($userId, $request);
        return new UserResource(User::fetchById($userId));
    }

    public function confirmUser(AuthController $authController, int $userId): JsonResource
    {
        $authController->confirm($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function deleteUser(
        AdminController $adminController,
        CampaignRepository $campaignRepository,
        int $userId,
    ): JsonResponse {
        return $adminController->deleteUser($userId, $campaignRepository);
    }

    public function denyAdvertising(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->denyAdvertising($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function denyPublishing(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->denyPublishing($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function grantAdvertising(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->grantAdvertising($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function grantPublishing(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->grantPublishing($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function switchUserToAgency(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->switchUserToAgency($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function switchUserToModerator(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->switchUserToModerator($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function switchUserToRegular(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->switchUserToRegular($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function unbanUser(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->unbanUser($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function resetHost(int $hostId): JsonResponse
    {
        $host = NetworkHost::find($hostId);
        if (null === $host) {
            throw new UnprocessableEntityHttpException('Invalid id');
        }

        $host->resetConnectionErrorCounter();

        return self::json();
    }

    public function addUser(Request $request): JsonResponse
    {
        $roles = self::getRoles($request);
        if (null === $roles) {
            throw new UnprocessableEntityHttpException('Field `role` is required');
        }
        $email = self::getEmailAddress($request);
        $walletAddress = self::getWalletAddress($request);
        if (null === $email && null === $walletAddress) {
            throw new UnprocessableEntityHttpException('Wallet address is required if email is not set');
        }
        $forcePasswordChange = self::forcePasswordChange($request);
        if ($forcePasswordChange && null === $email) {
            throw new UnprocessableEntityHttpException('Email is required if user should change password');
        }

        $data = [];

        DB::beginTransaction();
        try {
            $user = new User();
            $user->updateEmailWalletAndRoles($email, $walletAddress, $roles);
            $user->refresh();
            if (null !== $email) {
                $this->notifyUserAboutRegistration($user, $forcePasswordChange);
            }
            if (!$forcePasswordChange) {
                $password = substr(Hash::make(Str::random(8)), -8);
                $user->password = $password;
                $data = ['password' => $password];
            }
            $user->confirmAdmin();
            $user->saveOrFail();
            $id = $user->id;
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Error during user registration: (%s)', $throwable->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $data = array_merge((new UserResource(User::fetchById($id)))->toArray($request), $data);
        return self::json(['data' => $data]);
    }

    public function editUser(int $userId, Request $request): JsonResource
    {
        $user = User::fetchById($userId);
        if (null === $user) {
            throw new NotFoundHttpException('User not found');
        }

        $email = self::getEmailAddress($request);
        $walletAddress = self::getWalletAddress($request);
        $roles = self::getRoles($request);

        DB::beginTransaction();
        try {
            $user->updateEmailWalletAndRoles($email, $walletAddress, $roles);
            if (null !== $email) {
                $this->notifyUserAboutRegistration($user);
            }
            $user->confirmAdmin();
            $user->saveOrFail();
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Error during user editing: (%s)', $throwable->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new UserResource($user);
    }

    private static function validateLimit(array|string|null $limit): void
    {
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new UnprocessableEntityHttpException('Limit must be a positive integer');
        }
    }

    private static function validateEventFilters(?FilterCollection $filters): void
    {
        if (null === $filters) {
            return;
        }
        if (null !== ($filter = $filters->getFilterByName('type'))) {
            foreach ($filter->getValues() as $type) {
                if (null === ServerEventType::tryFrom($type)) {
                    throw new UnprocessableEntityHttpException(
                        sprintf('Filtering by type `%s` is not supported', $type)
                    );
                }
            }
        }
    }

    private static function validateUserOrderBy(?OrderByCollection $orderBy): void
    {
        if (null === $orderBy) {
            return;
        }

        $columns = array_map(fn($orderBy) => $orderBy->getColumn(), $orderBy->getOrderBy());
        foreach ($columns as $column) {
            if (
                !in_array(
                    $column,
                    [
                        'bonusBalance',
                        'campaignCount',
                        'connectedWallet',
                        'email',
                        'lastActiveAt',
                        'siteCount',
                        'walletBalance',
                    ],
                    true,
                )
            ) {
                throw new UnprocessableEntityHttpException(sprintf('Sorting by `%s` is not supported', $column));
            }
        }
    }

    private static function validateUserFilters(?FilterCollection $filters): void
    {
        if (null === $filters) {
            return;
        }
        if (null !== ($filter = $filters->getFilterByName('role'))) {
            $availableRoles = array_map(fn($role) => $role->value, Role::cases());
            foreach ($filter->getValues() as $role) {
                if (!in_array($role, $availableRoles, true)) {
                    throw new UnprocessableEntityHttpException(
                        sprintf('Filtering by role `%s` is not supported', $role)
                    );
                }
            }
        }
    }

    private static function getEmailAddress(Request $request): ?string
    {
        if (null === ($email = $request->input('email'))) {
            return null;
        }
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new UnprocessableEntityHttpException('Invalid email address');
        }
        if (null !== User::fetchByEmail($email)) {
            throw new UnprocessableEntityHttpException('Duplicated email address');
        }
        return $email;
    }

    private static function getRoles(Request $request): ?array
    {
        if (null === ($roles = $request->input('role'))) {
            return null;
        }
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        $availableRoles = array_map(fn($role) => $role->value, Role::cases());
        foreach ($roles as $role) {
            if (!in_array($role, $availableRoles, true)) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Role `%s` is not supported', $role)
                );
            }
        }
        if (in_array(Role::Agency->value, $roles, true) && in_array(Role::Moderator->value, $roles, true)) {
            throw new UnprocessableEntityHttpException(
                sprintf('User cannot have `%s` and `%s` roles together', Role::Agency->value, Role::Moderator->value)
            );
        }
        return $roles;
    }

    private static function getWalletAddress(Request $request): ?WalletAddress
    {
        if (
            null !== ($network = $request->input('wallet.network')) &&
            null !== ($address = $request->input('wallet.address'))
        ) {
            try {
                $walletAddress = new WalletAddress($network, $address);
            } catch (InvalidArgumentException) {
                throw new UnprocessableEntityHttpException('Invalid wallet address');
            }
            if (null !== User::fetchByWalletAddress($walletAddress)) {
                throw new UnprocessableEntityHttpException('Duplicated wallet address');
            }
        } else {
            $walletAddress = null;
        }
        return $walletAddress;
    }

    private static function forcePasswordChange(Request $request): mixed
    {
        if (
            null === ($forcePasswordChange = filter_var(
                $request->input('forcePasswordChange', false),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ))
        ) {
            throw new UnprocessableEntityHttpException('Field `forcePasswordChange` must be a boolean');
        }
        return $forcePasswordChange;
    }

    private function notifyUserAboutRegistration(User $user, bool $forcePasswordChange = false): void
    {
        if ($forcePasswordChange) {
            if (Token::canGenerateToken($user, Token::PASSWORD_RECOVERY)) {
                $mailable = new AuthRecovery(
                    Token::generate(Token::PASSWORD_RECOVERY, $user)->uuid,
                    self::ADPANEL_RESET_PASSWORD_URI
                );
                Mail::to($user)->queue($mailable);
            }
        } else {
            if (config('app.email_verification_required')) {
                $user->email_confirmed_at = null;
                $token = Token::generate(Token::EMAIL_ACTIVATE, $user);
                $mailable = new UserEmailActivate($token->uuid, self::ADPANEL_EMAIL_ACTIVATION_URI);
                Mail::to($user)->queue($mailable);
            } else {
                $user->confirmEmail();
            }
        }
    }
}

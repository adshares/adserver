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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\Common\LimitValidator;
use Adshares\Adserver\Http\Requests\Filter\DateFilter;
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Filter\FilterType;
use Adshares\Adserver\Http\Requests\Order\OrderByCollection;
use Adshares\Adserver\Http\Resources\GenericCollection;
use Adshares\Adserver\Http\Resources\HostResource;
use Adshares\Adserver\Http\Resources\UserResource;
use Adshares\Adserver\Mail\AuthRecovery;
use Adshares\Adserver\Mail\UserBanned;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Models\UserSettings;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Repository\Common\ServerEventLogRepository;
use Adshares\Adserver\Repository\Common\UserRepository;
use Adshares\Adserver\ViewModel\Role;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class ServerMonitoringController extends Controller
{
    private const ADPANEL_EMAIL_ACTIVATION_URI = '/auth/email-activation/';
    private const ADPANEL_RESET_PASSWORD_URI = '/auth/reset-password/';

    public function fetchEvents(Request $request, ServerEventLogRepository $repository): JsonResource
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'createdAt' => FilterType::Date,
            'type' => FilterType::String,
        ]);
        LimitValidator::validate($limit);
        self::validateEventFilters($filters);

        return (new GenericCollection($repository->fetchServerEvents($filters, $limit)))
            ->preserveQuery();
    }

    public function fetchEventTypes(ServerEventLogRepository $repository): JsonResponse
    {
        $types = $repository->fetchServerEventTypes();
        return new JsonResponse(['data' => $types]);
    }

    public function fetchHosts(Request $request): JsonResource
    {
        $limit = $request->query('limit', 10);
        LimitValidator::validate($limit);

        $paginator = (new NetworkHost())->orderBy('id')
            ->tokenPaginate($limit);

        return HostResource::collection($paginator)->preserveQuery();
    }

    public function fetchLatestEvents(Request $request, ServerEventLogRepository $repository): JsonResource
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'type' => FilterType::String,
        ]);
        LimitValidator::validate($limit);
        self::validateEventFilters($filters);

        return (new GenericCollection($repository->fetchLatestServerEvents($filters, $limit)))
            ->preserveQuery();
    }

    public function fetchTurnover(Request $request): JsonResponse
    {
        $filters = FilterCollection::fromRequest($request, [
            'date' => FilterType::Date,
        ]);
        /** @var DateFilter $dateFilter */
        $dateFilter = $filters?->getFilterByName('date');

        $data = [];
        foreach (TurnoverEntryType::cases() as $type) {
            $data[Str::camel($type->name)] = 0;
        }
        $entries = TurnoverEntry::fetchByHourTimestamp(
            $dateFilter?->getFrom() ?: new DateTimeImmutable('-1 month'),
            $dateFilter?->getTo() ?: new DateTimeImmutable(),
        );
        foreach ($entries as $entry) {
            $data[Str::camel($entry->type->name)] = (int)$entry->amount;
        }
        return self::json($data);
    }

    public function fetchTurnoverByType(string $type, Request $request): JsonResponse
    {
        $filters = FilterCollection::fromRequest($request, [
            'date' => FilterType::Date,
        ]);
        /** @var DateFilter $dateFilter */
        $dateFilter = $filters?->getFilterByName('date');
        $turnoverType = TurnoverEntryType::tryFrom($type) ?: throw new UnprocessableEntityHttpException('Invalid type');

        $data = TurnoverEntry::fetchByHourTimestampAndType(
            $dateFilter?->getFrom() ?: new DateTimeImmutable('-1 month'),
            $dateFilter?->getTo() ?: new DateTimeImmutable(),
            $turnoverType,
        );
        return self::json($data);
    }

    public function fetchTurnoverChart(string $resolution, Request $request): JsonResponse
    {
        $filters = FilterCollection::fromRequest($request, [
            'date' => FilterType::Date,
        ]);
        /** @var DateFilter $dateFilter */
        $dateFilter = $filters?->getFilterByName('date');

        $chartResolution = ChartResolution::tryFrom($resolution);
        if (
            null === $chartResolution || !in_array(
                $chartResolution,
                [ChartResolution::HOUR, ChartResolution::DAY, ChartResolution::WEEK, ChartResolution::MONTH]
            )
        ) {
            throw new UnprocessableEntityHttpException('Invalid resolution');
        }

        $entries = TurnoverEntry::fetchByHourTimestampForChart(
            $dateFilter?->getFrom() ?: new DateTimeImmutable('-1 month'),
            $dateFilter?->getTo() ?: new DateTimeImmutable(),
            $chartResolution,
        );

        return self::json($entries);
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
        LimitValidator::validate($limit);
        self::validateUserFilters($filters);
        self::validateUserOrderBy($orderBy);

        return UserResource::collection($userRepository->fetchUsers($filters, $orderBy, $limit))
            ->preserveQuery();
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

    public function banUser(Request $request, int $userId): JsonResource
    {
        $reason = $request->input('reason');
        if (!is_string($reason) || strlen(trim($reason)) < 1 || strlen(trim($reason)) > 255) {
            throw new UnprocessableEntityHttpException('Invalid reason');
        }
        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();
        if ($authenticatedUser->id === $userId) {
            throw new UnprocessableEntityHttpException('Cannot edit self');
        }
        $user = (new User())->findOrFail($userId);
        if (!$authenticatedUser->isAdmin() && ($user->isAdmin() || $user->isModerator())) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'User cannot be banned');
        }

        DB::beginTransaction();
        try {
            Campaign::deactivateAllForUserId($userId);
            $user->sites()->get()->each(
                function (Site $site) {
                    $site->changestatus(Site::STATUS_INACTIVE);
                    $site->push();
                }
            );
            $user->ban($reason);
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Exception during user ban: (%s)', $throwable->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Mail::to($user)->queue(new UserBanned($reason));

        return new UserResource($user);
    }

    public function confirmUser(AuthController $authController, int $userId): JsonResource
    {
        $user = $authController->confirm($userId);
        return new UserResource($user);
    }

    public function deleteUser(
        CampaignRepository $campaignRepository,
        int $userId,
    ): JsonResponse {
        $authenticatedUser = Auth::user();
        if ($authenticatedUser->id === $userId) {
            throw new UnprocessableEntityHttpException('Cannot delete self');
        }
        $user = (new User())->findOrFail($userId);
        if (!$authenticatedUser->isAdmin() && ($user->isAdmin() || $user->isModerator())) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'User cannot be deleted');
        }

        DB::beginTransaction();
        try {
            $campaigns = $campaignRepository->findByUserId($userId);
            foreach ($campaigns as $campaign) {
                $campaignRepository->delete($campaign);
            }
            BidStrategy::deleteByUserId($userId);

            $sites = $user->sites();
            foreach ($sites->get() as $site) {
                $site->zones()->delete();
            }
            $sites->delete();

            RefLink::fetchByUser($userId)->each(fn(RefLink $refLink) => $refLink->delete());
            Token::deleteByUserId($userId);
            Classification::deleteByUserId($userId);
            UserSettings::deleteByUserId($userId);

            $user->maskEmailAndWalletAddress();
            $user->clearApiKey();
            $user->delete();

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Exception during user deletion: (%s)', $throwable->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function denyAdvertising(int $userId): JsonResource
    {
        $user = $this->getRegularUserById($userId);
        DB::beginTransaction();
        try {
            Campaign::deactivateAllForUserId($userId);
            $user->is_advertiser = 0;
            $user->saveOrFail();
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Exception during deny advertising: (%s)', $throwable->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new UserResource($user);
    }

    public function denyPublishing(int $userId): JsonResource
    {
        $user = $this->getRegularUserById($userId);
        DB::beginTransaction();
        try {
            $user->sites()->get()->each(
                function (Site $site) {
                    $site->changestatus(Site::STATUS_INACTIVE);
                    $site->push();
                }
            );
            $user->is_publisher = 0;
            $user->saveOrFail();
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Exception during deny publishing: (%s)', $throwable->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new UserResource($user);
    }

    public function grantAdvertising(int $userId): JsonResource
    {
        $user = $this->getRegularUserById($userId);
        $user->is_advertiser = 1;
        $user->save();
        return new UserResource($user);
    }

    public function grantPublishing(int $userId): JsonResource
    {
        $user = $this->getRegularUserById($userId);
        $user->is_publisher = 1;
        $user->save();
        return new UserResource($user);
    }

    public function switchUserToAdmin(int $userId): JsonResource
    {
        $user = $this->getRegularUserById($userId);
        if ($user->campaigns()->count() > 0 || $user->sites()->count()) {
            throw new UnprocessableEntityHttpException('User has campaigns or sites');
        }
        $user->is_admin = true;
        $user->save();

        return new UserResource($user);
    }

    public function switchUserToAgency(int $userId): JsonResource
    {
        $user = $this->getRegularUserById($userId);
        $user->is_agency = true;
        $user->save();

        return new UserResource($user);
    }

    public function switchUserToModerator(int $userId): JsonResource
    {
        $user = $this->getRegularUserById($userId);
        if ($user->campaigns()->count() > 0 || $user->sites()->count()) {
            throw new UnprocessableEntityHttpException('User has campaigns or sites');
        }
        $user->is_moderator = true;
        $user->save();

        return new UserResource($user);
    }

    public function switchUserToRegular(int $userId): JsonResource
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();
        if ($authenticatedUser->id === $userId) {
            throw new UnprocessableEntityHttpException('Cannot edit self');
        }
        /** @var User $user */
        $user = (new User())->findOrFail($userId);
        if (!$authenticatedUser->isAdmin() && !$user->isAgency()) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }
        $user->is_admin = false;
        $user->is_moderator = false;
        $user->is_agency = false;
        $user->save();

        return new UserResource($user);
    }

    public function unbanUser(int $userId): JsonResource
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();
        if ($authenticatedUser->id === $userId) {
            throw new UnprocessableEntityHttpException('Cannot edit self');
        }
        $user = (new User())->findOrFail($userId);
        if (!$authenticatedUser->isAdmin() && ($user->isAdmin() || $user->isModerator())) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'User cannot be banned');
        }
        $user->unban();
        return new UserResource($user);
    }

    public function resetHost(int $hostId): JsonResponse
    {
        $host = (new NetworkHost())->find($hostId);
        if (null === $host) {
            throw new UnprocessableEntityHttpException('Invalid id');
        }

        $host->resetConnectionErrorCounter();

        return self::json();
    }

    public function addUser(Request $request): JsonResponse
    {
        $email = self::getEmailAddress($request);
        $walletAddress = self::getWalletAddress($request);
        if (null === $email && null === $walletAddress) {
            throw new UnprocessableEntityHttpException('Wallet address is required if email is not set');
        }
        $roles = config('app.default_user_roles');
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
        $user = (new User())->findOrFail($userId);
        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();
        if (
            !$authenticatedUser->isAdmin() &&
            $authenticatedUser->id !== $userId &&
            ($user->isAdmin() || $user->isModerator())
        ) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'User cannot be edited');
        }
        $email = self::getEmailAddress($request);
        $walletAddress = self::getWalletAddress($request);

        DB::beginTransaction();
        try {
            $user->updateEmailWalletAndRoles($email, $walletAddress);
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
                        'withdrawableBalance',
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

    private function getRegularUserById(int $userId): User
    {
        /** @var User $user */
        $user = (new User())->findOrFail($userId);
        if (!$user->isRegular()) {
            throw new UnprocessableEntityHttpException('User\'s account cannot be changed');
        }
        return $user;
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

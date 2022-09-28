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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Response\LicenseResponse;
use Adshares\Adserver\Mail\UserBanned;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserSettings;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Exception\RuntimeException;
use DateTimeInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AdminController extends Controller
{
    public function getSettings(): JsonResponse
    {
        return self::json([
            'settings' => [
                'ad_user_info_url' => config('app.aduser_info_url'),
            ],
        ]);
    }

    public function getLicense(LicenseVault $licenseVault): LicenseResponse
    {
        try {
            $license = $licenseVault->read();
        } catch (RuntimeException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        return new LicenseResponse($license);
    }

    public function getIndexUpdateTime(): JsonResponse
    {
        return self::json([
            'index_update_time' => Config::fetchDateTime(Config::PANEL_PLACEHOLDER_UPDATE_TIME)
                ->format(DateTimeInterface::ATOM),
        ]);
    }

    public function switchUserToModerator(int $userId): JsonResponse
    {
        /** @var User $logged */
        $logged = Auth::user();

        if (!$logged->isAdmin()) {
            return self::json([], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = User::find($userId);
        if (empty($user)) {
            return self::json([], Response::HTTP_NOT_FOUND);
        }

        if ($user->isModerator()) {
            return self::json([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->is_moderator = true;
        $user->is_agency = false;
        $user->save();

        return self::json($user->toArray());
    }

    public function switchUserToAgency(int $userId): JsonResponse
    {
        /** @var User $logged */
        $logged = Auth::user();

        if (!$logged->isModerator()) {
            return self::json([], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = User::find($userId);
        if (empty($user)) {
            return self::json([], Response::HTTP_NOT_FOUND);
        }

        if ($user->isAgency()) {
            return self::json([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->is_moderator = false;
        $user->is_agency = true;
        $user->save();

        return self::json($user->toArray());
    }

    public function switchUserToRegular(int $userId): JsonResponse
    {
        /** @var User $logged */
        $logged = Auth::user();

        if (!$logged->isModerator()) {
            return self::json([], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = User::find($userId);
        if (empty($user)) {
            return self::json([], Response::HTTP_NOT_FOUND);
        }

        if ($user->isModerator() && !$logged->isAdmin()) {
            return self::json([], Response::HTTP_FORBIDDEN);
        }

        $user->is_moderator = false;
        $user->is_agency = false;
        $user->save();

        return self::json($user->toArray());
    }

    public function banUser(int $userId, Request $request): JsonResponse
    {
        $reason = $request->input('reason');
        if (!is_string($reason) || strlen(trim($reason)) < 1 || strlen(trim($reason)) > 255) {
            throw new UnprocessableEntityHttpException('Invalid reason');
        }

        $user = $this->getRegularUserById($userId);

        DB::beginTransaction();
        try {
            Campaign::deactivateAllForUserId($userId);
            $user->sites()->get()->each(
                function (Site $site) {
                    $site->changestatus(Site::STATUS_INACTIVE);
                    $site->save();
                }
            );
            $user->ban($reason);
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error(sprintf('Exception during user ban: (%s)', $exception->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Mail::to($user)->queue(new UserBanned($reason));

        return self::json($user->toArray());
    }

    public function unbanUser(int $userId): JsonResponse
    {
        $user = $this->getRegularUserById($userId);
        $user->unban();

        return self::json($user->toArray());
    }

    public function deleteUser(int $userId, CampaignRepository $campaignRepository): JsonResponse
    {
        $user = $this->getRegularUserById($userId);

        DB::beginTransaction();
        try {
            $campaigns = $campaignRepository->findByUserId($userId);
            foreach ($campaigns as $campaign) {
                $campaign->conversions()->delete();
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
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error(sprintf('Exception during user deletion: (%s)', $exception->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function grantAdvertising(int $userId): JsonResponse
    {
        $user = $this->getRegularUserById($userId);
        $user->is_advertiser = 1;
        $user->save();
        return self::json($user->toArray());
    }

    public function denyAdvertising(int $userId): JsonResponse
    {
        $user = $this->getRegularUserById($userId);
        $user->is_advertiser = 0;
        $user->save();
        return self::json($user->toArray());
    }

    public function grantPublishing(int $userId): JsonResponse
    {
        $user = $this->getRegularUserById($userId);
        $user->is_publisher = 1;
        $user->save();
        return self::json($user->toArray());
    }

    public function denyPublishing(int $userId): JsonResponse
    {
        $user = $this->getRegularUserById($userId);
        $user->is_publisher = 0;
        $user->save();
        return self::json($user->toArray());
    }

    private function getRegularUserById(int $userId): User
    {
        /** @var User $user */
        $user = (new User())->find($userId);
        if (empty($user)) {
            throw new NotFoundHttpException();
        }
        if ($user->isAdmin()) {
            throw new UnprocessableEntityHttpException('Administrator account cannot be changed');
        }

        return $user;
    }
}

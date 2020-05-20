<?php declare(strict_types = 1);
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
use Adshares\Adserver\Http\Requests\BidStrategyRequest;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BidStrategyController extends Controller
{
    public function getBidStrategyUuidDefault(): JsonResponse
    {
        return self::json(['uuid' => Config::fetchStringOrFail(Config::BID_STRATEGY_UUID_DEFAULT)]);
    }

    public function putBidStrategyUuidDefault(Request $request): JsonResponse
    {
        $bidStrategyPublicId = $request->input('uuid');
        if (!Utils::isUuidValid($bidStrategyPublicId)) {
            throw new UnprocessableEntityHttpException(
                sprintf('Invalid id (%s)', $bidStrategyPublicId)
            );
        }

        $bidStrategy = BidStrategy::fetchByPublicId($bidStrategyPublicId);
        if (null === $bidStrategy) {
            throw new NotFoundHttpException(sprintf('BidStrategy (%s) does not exist.', $bidStrategyPublicId));
        }
        if (BidStrategy::ADMINISTRATOR_ID !== $bidStrategy->user_id) {
            throw new HttpException(
                JsonResponse::HTTP_FORBIDDEN,
                sprintf('Cannot set bid strategy uuid (%s) as default', $bidStrategyPublicId)
            );
        }

        Config::upsertByKey(Config::BID_STRATEGY_UUID_DEFAULT, $bidStrategyPublicId);

        return self::json([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function getBidStrategy(): JsonResponse
    {
        return self::json(BidStrategy::fetchForUser(Auth::user()->id));
    }

    public function putBidStrategy(BidStrategyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $input = $request->toArray();

        try {
            DB::beginTransaction();

            $bidStrategy = BidStrategy::register($input['name'], $isAdmin ? BidStrategy::ADMINISTRATOR_ID : $user->id);
            $bidStrategyDetails = [];
            foreach ($input['details'] as $detail) {
                $bidStrategyDetails[] = BidStrategyDetail::create($detail['category'], (float)$detail['rank']);
            }
            $bidStrategy->bidStrategyDetails()->saveMany($bidStrategyDetails);

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::debug(
                sprintf('BidStrategy (%s) could not be added (%s).', $input['name'], $exception->getMessage())
            );

            throw new HttpException(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'Cannot add bid strategy');
        }

        return self::json(['uuid' => $bidStrategy->uuid], JsonResponse::HTTP_CREATED);
    }

    public function patchBidStrategy(string $bidStrategyPublicId, BidStrategyRequest $request): JsonResponse
    {
        if (!Utils::isUuidValid($bidStrategyPublicId)) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid id (%s)', $bidStrategyPublicId));
        }

        /** @var User $user */
        $user = Auth::user();
        $bidStrategy = BidStrategy::fetchByPublicId($bidStrategyPublicId);

        if (null === $bidStrategy) {
            throw new NotFoundHttpException(sprintf('BidStrategy (%s) does not exist.', $bidStrategyPublicId));
        }
        if ($bidStrategy->user_id !== $user->id
            && !($bidStrategy->user_id === BidStrategy::ADMINISTRATOR_ID
                && $user->isAdmin())) {
            throw new HttpException(
                JsonResponse::HTTP_UNAUTHORIZED,
                sprintf('BidStrategy (%s) could not be edited.', $bidStrategyPublicId)
            );
        }

        $input = $request->toArray();

        try {
            DB::beginTransaction();

            $bidStrategy->name = $input['name'];
            $bidStrategy->save();
            $bidStrategy->bidStrategyDetails()->delete();
            $bidStrategyDetails = [];
            foreach ($input['details'] as $detail) {
                $bidStrategyDetails[] = BidStrategyDetail::create($detail['category'], (float)$detail['rank']);
            }
            $bidStrategy->bidStrategyDetails()->saveMany($bidStrategyDetails);

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::debug(
                sprintf('BidStrategy (%s) could not be edited (%s).', $input['name'], $exception->getMessage())
            );

            throw new HttpException(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'Cannot edit bid strategy');
        }

        return self::json([], JsonResponse::HTTP_NO_CONTENT);
    }
}

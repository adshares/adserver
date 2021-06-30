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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\BidStrategyRequest;
use Adshares\Adserver\Http\Response\Stats\BidStrategySpreadsheetResponse;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\ViewModel\OptionsSelector;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as PhpSpreadsheetException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BidStrategyController extends Controller
{
    private const MAXIMAL_BID_STRATEGY_COUNT_PER_USER = 20;

    private const MIME_TYPE_SPREADSHEET = [
        'application/octet-stream',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    /** @var ConfigurationRepository */
    private $configurationRepository;

    public function __construct(ConfigurationRepository $configurationRepository)
    {
        $this->configurationRepository = $configurationRepository;
    }

    public function getBidStrategyUuidDefault(): JsonResponse
    {
        return self::json(['uuid' => Config::fetchStringOrFail(Config::BID_STRATEGY_UUID_DEFAULT)]);
    }

    public function putBidStrategyUuidDefault(Request $request): JsonResponse
    {
        $previousBidStrategyPublicId = Config::fetchStringOrFail(Config::BID_STRATEGY_UUID_DEFAULT);
        $bidStrategyPublicId = $request->input('uuid');
        if (!Utils::isUuidValid($bidStrategyPublicId)) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid id (%s)', $bidStrategyPublicId));
        }

        $bidStrategy = BidStrategy::fetchByPublicId($bidStrategyPublicId);
        if (null === $bidStrategy) {
            throw new NotFoundHttpException('Bid strategy does not exist.');
        }
        if (BidStrategy::ADMINISTRATOR_ID !== $bidStrategy->user_id) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Cannot set bid strategy as default.');
        }

        if ($bidStrategyPublicId != $previousBidStrategyPublicId) {
            DB::beginTransaction();
            try {
                Config::upsertByKey(Config::BID_STRATEGY_UUID_DEFAULT, $bidStrategyPublicId);
                Campaign::updateBidStrategyUuid($bidStrategyPublicId, $previousBidStrategyPublicId);
                DB::commit();
            } catch (Exception $exception) {
                DB::rollBack();
                Log::debug(sprintf('Default Bid strategy could not be changed (%s).', $exception->getMessage()));

                throw new HttpException(
                    JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                    'Cannot change default bid strategy.'
                );
            }
        }

        return self::json([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function getBidStrategy(Request $request): JsonResponse
    {
        $attachDefault = filter_var($request->input('attach-default', false), FILTER_VALIDATE_BOOLEAN);
        /** @var User $user */
        $user = Auth::user();
        $userId = $user->isAdmin() ? BidStrategy::ADMINISTRATOR_ID : $user->id;

        $bidStrategyCollection = BidStrategy::fetchForUser($userId);

        if ($attachDefault) {
            $defaultBidStrategyPublicId = Config::fetchStringOrFail(Config::BID_STRATEGY_UUID_DEFAULT);
            $defaultBidStrategy = BidStrategy::fetchByPublicId($defaultBidStrategyPublicId);
            $bidStrategyCollection->push($defaultBidStrategy);
        }

        return self::json($bidStrategyCollection);
    }

    public function getBidStrategySpreadsheet(string $bidStrategyPublicId): StreamedResponse
    {
        $bidStrategy = $this->fetchBidStrategy($bidStrategyPublicId);

        $targetingOptions = $this->configurationRepository->fetchTargetingOptions();
        $targetingSchema = (new OptionsSelector($targetingOptions))->toArray();

        $bidStrategyDetails = $bidStrategy->bidStrategyDetails->pluck('rank', 'category')->toArray();
        $data = self::processTargetingOptions($targetingSchema, $bidStrategyDetails);

        return (new BidStrategySpreadsheetResponse($bidStrategy, $data, (string)config('app.name')))->responseStream();
    }

    public function putBidStrategySpreadsheet(string $bidStrategyPublicId, Request $request): JsonResponse
    {
        $bidStrategy = $this->fetchBidStrategy($bidStrategyPublicId);

        if (null === ($file = $request->file('file'))) {
            throw new BadRequestHttpException('File is required');
        }
        if (!in_array($mimeType = $file->getMimeType(), self::MIME_TYPE_SPREADSHEET, true)) {
            throw new UnprocessableEntityHttpException(sprintf('Unsupported mime type (%s).', $mimeType));
        }

        $pathName = $file->getPathname();

        try {
            $reader = IOFactory::createReaderForFile($pathName);
        } catch (PhpSpreadsheetException $e) {
            throw new UnprocessableEntityHttpException('Unable to read file.');
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($pathName);

        $sheets = $spreadsheet->getAllSheets();
        $sheetsCount = count($sheets);

        $bidStrategyDetails = [];
        for ($i = 1; $i < $sheetsCount; $i++) {
            $sheet = $sheets[$i];
            $row = 2;

            while (null !== ($value = $sheet->getCellByColumnAndRow(3, $row)->getValue())) {
                if (100 !== $value) {
                    $category = $sheet->getCellByColumnAndRow(1, $row)->getValue();
                    $bidStrategyDetails[] = BidStrategyDetail::create($category, (float)$value / 100);
                }

                ++$row;
            }
        }

        $this->editBidStrategy($bidStrategy, null, $bidStrategyDetails);

        return self::json([], JsonResponse::HTTP_NO_CONTENT);
    }

    private static function processTargetingOptions(
        array $options,
        array $bidStrategyDetails,
        string $parentKey = '',
        string $parentLabel = ''
    ): array {
        $result = [];

        foreach ($options as $option) {
            $key = ('' === $parentKey) ? $option['key'] : $parentKey . ':' . $option['key'];
            $label = ('' === $parentLabel) ? $option['label'] : $parentLabel . '|' . $option['label'];

            if (isset($option['children'])) {
                array_push(
                    $result,
                    ...self::processTargetingOptions($option['children'], $bidStrategyDetails, $key, $label)
                );
            }
            if (isset($option['values'])) {
                $result[] = [
                    'label' => $label,
                    'data' => self::processTargetingOptionValues($option['values'], $bidStrategyDetails, $key),
                ];
            }
        }

        return $result;
    }

    private static function processTargetingOptionValues(
        array $optionValues,
        array $bidStrategyDetails,
        string $parentKey = '',
        string $parentLabel = ''
    ): array {
        $result = [];

        foreach ($optionValues as $optionValue) {
            $key = ('' === $parentKey) ? $optionValue['value'] : $parentKey . ':' . $optionValue['value'];
            $label = ('' === $parentLabel) ? $optionValue['label'] : $parentLabel . '/' . $optionValue['label'];

            $result[] = [
                'key' => $key,
                'label' => $label,
                'value' => ($bidStrategyDetails[$key] ?? 1) * 100,
            ];

            if (isset($optionValue['values'])) {
                array_push(
                    $result,
                    ...self::processTargetingOptionValues(
                        $optionValue['values'],
                        $bidStrategyDetails,
                        $parentKey,
                        $label
                    )
                );
            }
        }

        return $result;
    }

    public function putBidStrategy(BidStrategyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $userId = $isAdmin ? BidStrategy::ADMINISTRATOR_ID : $user->id;
        if (self::MAXIMAL_BID_STRATEGY_COUNT_PER_USER <= BidStrategy::countByUserId($userId)) {
            throw new UnprocessableEntityHttpException(
                sprintf(
                    'Maximal bid strategy count (%d) reached. Delete unused.',
                    self::MAXIMAL_BID_STRATEGY_COUNT_PER_USER
                )
            );
        }

        $input = $request->toArray();
        $bidStrategyDetails = [];
        foreach ($input['details'] as $detail) {
            $bidStrategyDetails[] = BidStrategyDetail::create($detail['category'], (float)$detail['rank']);
        }

        DB::beginTransaction();

        try {
            $bidStrategy = BidStrategy::register($input['name'], $userId);
            $bidStrategy->bidStrategyDetails()->saveMany($bidStrategyDetails);

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::debug(
                sprintf('Bid strategy (%s) could not be added (%s).', $input['name'], $exception->getMessage())
            );

            throw new HttpException(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'Cannot add bid strategy.');
        }

        return self::json(['uuid' => $bidStrategy->uuid], JsonResponse::HTTP_CREATED);
    }

    public function patchBidStrategy(string $bidStrategyPublicId, BidStrategyRequest $request): JsonResponse
    {
        $bidStrategy = $this->fetchBidStrategy($bidStrategyPublicId);
        $input = $request->toArray();
        $name = $input['name'];
        $bidStrategyDetails = [];
        foreach ($input['details'] as $detail) {
            $bidStrategyDetails[] = BidStrategyDetail::create($detail['category'], (float)$detail['rank']);
        }

        $this->editBidStrategy($bidStrategy, $name, $bidStrategyDetails);

        return self::json([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function deleteBidStrategy(string $bidStrategyPublicId): JsonResponse
    {
        $bidStrategy = $this->fetchBidStrategy($bidStrategyPublicId);

        if (Campaign::isBidStrategyUsed($bidStrategy->uuid)) {
            throw new UnprocessableEntityHttpException('The bid strategy is used and therefore cannot be deleted.');
        }
        if ($bidStrategy->uuid === Config::fetchStringOrFail(Config::BID_STRATEGY_UUID_DEFAULT)) {
            throw new UnprocessableEntityHttpException('Default bid strategy cannot be deleted.');
        }

        DB::beginTransaction();
        try {
            $bidStrategy->bidStrategyDetails()->delete();
            $bidStrategy->delete();

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::debug(
                sprintf('Bid strategy (%d) could not be deleted (%s).', $bidStrategy->id, $exception->getMessage())
            );

            throw new HttpException(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'Cannot delete bid strategy.');
        }

        return self::json([], JsonResponse::HTTP_NO_CONTENT);
    }

    private function fetchBidStrategy(string $bidStrategyPublicId): BidStrategy
    {
        if (!Utils::isUuidValid($bidStrategyPublicId)) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid id (%s)', $bidStrategyPublicId));
        }

        /** @var User $user */
        $user = Auth::user();
        $bidStrategy = BidStrategy::fetchByPublicId($bidStrategyPublicId);

        if (null === $bidStrategy) {
            throw new NotFoundHttpException('Bid strategy does not exist.');
        }
        if (
            $bidStrategy->user_id !== $user->id
            && !($bidStrategy->user_id === BidStrategy::ADMINISTRATOR_ID
                && $user->isAdmin())
        ) {
            throw new UnprocessableEntityHttpException(
                sprintf('Bid strategy (%s) could not be accessed.', $bidStrategy->name)
            );
        }

        return $bidStrategy;
    }

    private function editBidStrategy(BidStrategy $bidStrategy, ?string $name, array $bidStrategyDetails): void
    {
        DB::beginTransaction();

        try {
            if (null !== $name) {
                $bidStrategy->name = $name;
                $bidStrategy->save();
            }
            $bidStrategy->bidStrategyDetails()->forceDelete();
            $bidStrategy->bidStrategyDetails()->saveMany($bidStrategyDetails);

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::debug(
                sprintf('Bid strategy (%d) could not be edited (%s).', $bidStrategy->id, $exception->getMessage())
            );

            throw new HttpException(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'Cannot edit bid strategy.');
        }
    }
}

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

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Requests\BidStrategyRequest;
use Adshares\Adserver\Http\Response\Stats\BidStrategySpreadsheetResponse;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Common\Application\Dto\TaxonomyV2\Targeting;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\Response;
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

    private ConfigurationRepository $configurationRepository;

    public function __construct(ConfigurationRepository $configurationRepository)
    {
        $this->configurationRepository = $configurationRepository;
    }

    public function getBidStrategyUuidDefault(string $medium, Request $request): JsonResponse
    {
        $vendor = $request->get('vendor');
        return self::json(['uuid' => BidStrategy::fetchDefault($medium, $vendor)->uuid]);
    }

    public function patchBidStrategyUuidDefault(string $medium, Request $request): JsonResponse
    {
        $vendor = $request->get('vendor');
        $bidStrategyPublicId = $request->input('uuid');
        if (!Utils::isUuidValid($bidStrategyPublicId)) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid id (%s)', $bidStrategyPublicId));
        }

        $bidStrategy = BidStrategy::fetchByPublicId($bidStrategyPublicId);
        if (null === $bidStrategy) {
            throw new NotFoundHttpException('Bid strategy does not exist.');
        }
        if ($bidStrategy->medium !== $medium || $bidStrategy->vendor !== $vendor) {
            throw new UnprocessableEntityHttpException('Invalid medium/vendor');
        }
        if (BidStrategy::ADMINISTRATOR_ID !== $bidStrategy->user_id) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Cannot set bid strategy as default.');
        }

        $previousBidStrategy = BidStrategy::fetchDefault($medium, $vendor);
        $previousBidStrategyPublicId = $previousBidStrategy->uuid;

        if ($bidStrategyPublicId != $previousBidStrategyPublicId) {
            DB::beginTransaction();
            try {
                $bidStrategy->setDefault(true);
                $previousBidStrategy->setDefault(false);
                Campaign::updateBidStrategyUuid($bidStrategyPublicId, $previousBidStrategyPublicId);
                DB::commit();
            } catch (Exception $exception) {
                DB::rollBack();
                Log::debug(sprintf('Default Bid strategy could not be changed (%s).', $exception->getMessage()));

                throw new HttpException(
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    'Cannot change default bid strategy.'
                );
            }
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function getBidStrategies(string $medium, Request $request): JsonResponse
    {
        $vendor = $request->get('vendor');
        $attachDefault = filter_var($request->input('attach-default', false), FILTER_VALIDATE_BOOLEAN);
        /** @var User $user */
        $user = Auth::user();
        $userId = $user->isAdmin() ? BidStrategy::ADMINISTRATOR_ID : $user->id;

        $bidStrategyCollection = BidStrategy::fetchForUser($userId, $medium, $vendor);

        if ($attachDefault) {
            $defaultBidStrategy = BidStrategy::fetchDefault($medium, $vendor);
            $bidStrategyCollection->push($defaultBidStrategy);
        }

        return self::json($bidStrategyCollection);
    }

    public function getBidStrategySpreadsheet(string $bidStrategyPublicId): StreamedResponse
    {
        $bidStrategy = $this->fetchBidStrategy($bidStrategyPublicId);

        $targeting = $this->configurationRepository->fetchMedium()->getTargeting();
        $bidStrategyDetails = $bidStrategy->bidStrategyDetails->pluck('rank', 'category')->toArray();
        $data = self::processTargeting($targeting, $bidStrategyDetails);

        return (new BidStrategySpreadsheetResponse($bidStrategy, $data, config('app.adserver_name')))
            ->responseStream();
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
        $bidStrategyDetails = $this->getBidStrategyDetailsFromSpreadsheet($spreadsheet);

        $this->editBidStrategy($bidStrategy, null, $bidStrategyDetails);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    private static function processTargeting(Targeting $targeting, array $bidStrategyDetails): array
    {
        $result = [];
        $targetingArray = $targeting->toArray();
        $targetingRootKeys = [
            'user' => 'User',
            'site' => 'Site',
            'device' => 'Device',
        ];

        foreach ($targetingRootKeys as $rootKey => $rootLabel) {
            foreach ($targetingArray[$rootKey] as $option) {
                $key = $rootKey . ':' . $option['name'];
                $label = $rootLabel . '|' . $option['label'];
                $result[] = [
                    'label' => $label,
                    'data' => self::processTargetingOptionValues(
                        $option['items'] ?? [],
                        $bidStrategyDetails,
                        $key
                    ),
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

        if (empty($optionValues)) {
            $optionValues = self::prepareOptionForInputType($bidStrategyDetails, $parentKey);
        }

        foreach ($optionValues as $optionKey => $option) {
            $optionLabel = is_array($option) ? $option['label'] : $option;
            $key = ('' === $parentKey) ? $optionKey : $parentKey . ':' . $optionKey;
            $label = ('' === $parentLabel) ? $optionLabel : $parentLabel . '/' . $optionLabel;

            $result[] = [
                'key' => $key,
                'label' => $label,
                'value' => ($bidStrategyDetails[$key] ?? 1) * 100,
            ];

            if (is_array($option)) {
                array_push(
                    $result,
                    ...self::processTargetingOptionValues($option['values'], $bidStrategyDetails, $parentKey, $label)
                );
            }
        }

        return $result;
    }

    private static function prepareOptionForInputType(array $bidStrategyDetails, string $parentKey): array
    {
        $optionValues = [
            '' => 'MISSING',
            '*' => 'DEFAULT',
        ];
        foreach ($bidStrategyDetails as $key => $value) {
            $idParts = explode(':', $key);
            $id = array_pop($idParts);
            $prefix = implode(':', $idParts);

            if ($parentKey === $prefix && !isset($optionValues[$id])) {
                $optionValues[$id] = '';
            }
        }
        return $optionValues;
    }

    public function putBidStrategy(string $medium, BidStrategyRequest $request): JsonResponse
    {
        $vendor = $request->get('vendor');
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
            $bidStrategy = BidStrategy::register($input['name'], $userId, $medium, $vendor);
            $bidStrategy->bidStrategyDetails()->saveMany($bidStrategyDetails);

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::debug(
                sprintf('Bid strategy (%s) could not be added (%s).', $input['name'], $exception->getMessage())
            );

            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Cannot add bid strategy.');
        }

        return self::json(['uuid' => $bidStrategy->uuid], Response::HTTP_CREATED);
    }

    public function patchBidStrategy(string $bidStrategyPublicId, BidStrategyRequest $request): JsonResponse
    {
        $bidStrategy = $this->fetchBidStrategy($bidStrategyPublicId);
        $input = $request->toArray();
        $name = $input['name'];
        $bidStrategyDetails = [];

        $bidStrategyDetailsArray = $bidStrategy->bidStrategyDetails->pluck('rank', 'category')->toArray();
        foreach ($input['details'] as $detail) {
            $bidStrategyDetailsArray[$detail['category']] = $detail['rank'];
        }

        foreach ($bidStrategyDetailsArray as $category => $rank) {
            $bidStrategyDetails[] = BidStrategyDetail::create($category, (float)$rank);
        }

        $this->editBidStrategy($bidStrategy, $name, $bidStrategyDetails);

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function deleteBidStrategy(string $bidStrategyPublicId): JsonResponse
    {
        $bidStrategy = $this->fetchBidStrategy($bidStrategyPublicId);

        if (Campaign::isBidStrategyUsed($bidStrategy->uuid)) {
            throw new UnprocessableEntityHttpException('The bid strategy is used and therefore cannot be deleted.');
        }
        if ($bidStrategy->is_default) {
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

            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Cannot delete bid strategy.');
        }

        return self::json([], Response::HTTP_NO_CONTENT);
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

            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Cannot edit bid strategy.');
        }
    }

    private function getBidStrategyDetailsFromSpreadsheet(Spreadsheet $spreadsheet): array
    {
        $columnPrefix = 1;
        $columnId = 2;
        $columnValue = 4;
        $rowFirstDataRow = 2;
        $defaultValue = 100;

        $bidStrategyDetails = [];

        $sheets = $spreadsheet->getAllSheets();
        $sheetsCount = count($sheets);

        $duplicates = [];
        for ($i = 1; $i < $sheetsCount; $i++) {
            $sheet = $sheets[$i];

            $removeDefaults = true;

            $row = $rowFirstDataRow;
            while (null !== $sheet->getCellByColumnAndRow($columnValue, $row)->getValue()) {
                if ($sheet->getCellByColumnAndRow($columnId, $row)->getValue() === '*') {
                    $removeDefaults = false;
                    break;
                }
                ++$row;
            }

            $row = $rowFirstDataRow;
            while (null !== ($value = $sheet->getCellByColumnAndRow($columnValue, $row)->getValue())) {
                if (!$removeDefaults || $value !== $defaultValue) {
                    $category = $sheet->getCellByColumnAndRow($columnPrefix, $row)->getValue()
                        . ':' . $sheet->getCellByColumnAndRow($columnId, $row)->getValue();
                    if (!isset($duplicates[$category])) {
                        $bidStrategyDetails[] = BidStrategyDetail::create($category, (float)$value / 100);
                    }
                    $duplicates[$category] = true;
                }

                ++$row;
            }
        }
        return $bidStrategyDetails;
    }
}

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

namespace Adshares\Adserver\Repository;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;
use Throwable;

class CampaignRepository
{
    public function __construct(
        private readonly BannerClassificationCreator $bannerClassificationCreator,
        private readonly ExchangeRateReader $exchangeRateReader,
    ) {
    }

    public function find(?FilterCollection $filters = null): Collection
    {
        $builder = (new Campaign())->with('conversions');
        if (null !== $filters) {
            foreach ($filters->getFilters() as $filter) {
                $builder->whereIn($filter->getName(), $filter->getValues());
            }
        }
        return $builder->get();
    }

    /**
     * @return Collection|Campaign[]
     */
    public function findByUserId(int $userId): Collection
    {
        return (new Campaign())->where('user_id', $userId)->get();
    }

    /**
     * @return Collection|Campaign[]
     */
    public function fetchActiveCampaigns(): Collection
    {
        $query = Campaign::where('campaigns.status', Campaign::STATUS_ACTIVE);

        $query->where(
            function ($q) {
                $q->where('campaigns.time_end', '>', new DateTime())
                    ->orWhere('campaigns.time_end', null);
            }
        );

        return $query->with('banners')->get();
    }

    /**
     * @param array $campaignIds
     * @return Collection|Campaign[]
     */
    public function fetchCampaignByIds(array $campaignIds): Collection
    {
        return Campaign::whereIn('id', $campaignIds)->with('banners')->get();
    }

    public function fetchCampaignById(int $campaignId): Campaign
    {
        return (new Campaign())->with('banners')->findOrFail($campaignId);
    }

    public function fetchCampaignByIdWithConversions(int $campaignId): Campaign
    {
        return (new Campaign())->with('conversions')->findOrFail($campaignId);
    }

    /**
     * @param Campaign $campaign
     * @param array $banners
     * @param array $conversions
     *
     * @return Campaign
     *
     * @throws RuntimeException
     */
    public function save(Campaign $campaign, array $banners = [], array $conversions = []): Campaign
    {
        if (Campaign::STATUS_ACTIVE === $campaign->status && empty($banners)) {
            throw new InvalidArgumentException('Cannot save active campaign without creatives');
        }
        DB::beginTransaction();
        $status = $campaign->status;
        $campaign->status = Campaign::STATUS_DRAFT;
        if (Campaign::STATUS_DRAFT !== $status && !$campaign->changeStatus($status, $this->fetchExchangeRateOrFail())) {
            throw new InvalidArgumentException('Insufficient funds');
        }

        try {
            $campaign->save();

            if ($banners) {
                foreach ($banners as $banner) {
                    $campaign->banners()->save($banner);
                }
            }

            if ($conversions) {
                foreach ($conversions as $conversion) {
                    $campaign->conversions()->save($conversion);
                }
            }

            $this->bannerClassificationCreator->createForCampaign($campaign);
            $campaign->refresh();
            DB::commit();
        } catch (InvalidArgumentException $exception) {
            DB::rollBack();
            throw $exception;
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Campaign save failed (%s)', $throwable->getMessage()));
            throw new RuntimeException('Campaign save failed');
        }

        return $campaign;
    }

    public function delete(Campaign $campaign): void
    {
        DB::beginTransaction();
        try {
            if (Campaign::STATUS_INACTIVE !== $campaign->status) {
                $campaign->status = Campaign::STATUS_INACTIVE;
                $this->update($campaign);
            }
            $campaign->conversions()->delete();
            $campaign->delete();
            foreach ($campaign->banners as $banner) {
                $banner->classifications()->delete();
            }
            $campaign->banners()->delete();
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Campaign deletion failed (%s)', $throwable->getMessage()));
            throw new RuntimeException('Campaign deletion failed');
        }
    }

    public function update(
        Campaign $campaign,
        array $bannersToInsert = [],
        array $bannersToUpdate = [],
        array $bannersToDelete = [],
        array $conversionsToInsert = [],
        array $conversionsToUpdate = [],
        array $conversionUuidsToDelete = [],
    ): Campaign {
        if (!$campaign->exists()) {
            throw new RuntimeException('Function `update` requires existing Campaign model');
        }
        if (isset($campaign->getDirty()['bid_strategy_uuid'])) {
            self::checkIfBidStrategyCanChanged($campaign);
        }
        DB::beginTransaction();
        $status = $campaign->status;
        $campaign->status = Campaign::STATUS_INACTIVE;
        if (
            Campaign::STATUS_INACTIVE !== $status && !$campaign->changeStatus($status, $this->fetchExchangeRateOrFail())
        ) {
            throw new InvalidArgumentException('Insufficient funds');
        }

        try {
            $campaign->update();

            if ($bannersToInsert) {
                foreach ($bannersToInsert as $banner) {
                    $campaign->banners()->save($banner);
                }
            }

            if ($bannersToUpdate) {
                foreach ($bannersToUpdate as $banner) {
                    $banner->update();
                }
            }

            if ($bannersToDelete) {
                foreach ($bannersToDelete as $banner) {
                    $banner->classifications()->delete();
                    $banner->delete();
                }
            }

            if ($conversionsToInsert) {
                foreach ($conversionsToInsert as $conversion) {
                    $campaign->conversions()->save($conversion);
                }
            }

            if ($conversionsToUpdate) {
                foreach ($conversionsToUpdate as $conversion) {
                    $conversion->update();
                }
            }

            if ($conversionUuidsToDelete) {
                $conversionBinaryUuidsToDelete = array_map(
                    function ($uuid) {
                        return hex2bin($uuid);
                    },
                    $conversionUuidsToDelete
                );

                $campaign->conversions()->whereIn('uuid', $conversionBinaryUuidsToDelete)->delete();
            }

            $this->bannerClassificationCreator->createForCampaign($campaign);
            $campaign->refresh();

            if (
                Campaign::STATUS_ACTIVE === $campaign->status
                && !$campaign->banners()->where('status', Banner::STATUS_ACTIVE)->exists()
            ) {
                throw new InvalidArgumentException('Cannot update active campaign without creatives');
            }
            DB::commit();
        } catch (InvalidArgumentException $exception) {
            DB::rollBack();
            throw $exception;
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Campaign update failed (%s)', $throwable->getMessage()));
            throw new RuntimeException('Campaign update failed');
        }
        return $campaign;
    }

    public function fetchCampaignByUuid(UuidInterface $id): Campaign
    {
        if (null === ($campaign = Campaign::fetchByUuid(str_replace('-', '', $id->toString())))) {
            throw new ModelNotFoundException(sprintf('No query results for campaign %s', $id->toString()));
        }
        return $campaign;
    }

    public function fetchBannerByUuid(Campaign $campaign, UuidInterface $bannerId): Banner
    {
        if (null === ($banner = $campaign->banners()->where('uuid', $bannerId->getBytes())->first())) {
            throw new ModelNotFoundException(sprintf('No query results for banner %s', $bannerId->toString()));
        }
        return $banner;
    }

    public function fetchBanners(Campaign $campaign, ?int $perPage = null): CursorPaginator
    {
        return $campaign->banners()->orderBy('id')
            ->tokenPaginate($perPage);
    }

    public function fetchCampaigns(?FilterCollection $filters = null, ?int $perPage = null): CursorPaginator
    {
        $builder = Campaign::query();
        if (null !== $filters) {
            foreach ($filters->getFilters() as $filter) {
                $builder->whereIn($filter->getName(), $filter->getValues());
            }
        }
        return $builder->orderBy('id')
            ->tokenPaginate($perPage);
    }

    private static function checkIfBidStrategyCanChanged(Campaign $campaign): void
    {
        $userId = $campaign->user_id;
        $bidStrategy = BidStrategy::fetchByPublicId($campaign->bid_strategy_uuid);
        if (
            $bidStrategy === null
            || ($bidStrategy->user_id !== $userId && $bidStrategy->user_id !== BidStrategy::ADMINISTRATOR_ID)
        ) {
            throw new InvalidArgumentException('Bid strategy could not be accessed');
        }
    }

    private function fetchExchangeRateOrFail(): ExchangeRate
    {
        if (Currency::ADS !== Currency::from(config('app.currency'))) {
            return ExchangeRate::ONE(Currency::ADS);
        }

        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();
        } catch (ExchangeRateNotAvailableException $exception) {
            Log::error(sprintf('Exchange rate is not available (%s)', $exception->getMessage()));
            throw new RuntimeException('Exchange rate is not available');
        }
        return $exchangeRate;
    }

    public function fetchCampaignsMedia(): Collection
    {
        return Campaign::query()
            ->select(['medium', 'vendor'])
            ->groupBy(['medium', 'vendor'])
            ->orderBy('medium')
            ->orderBy('vendor')
            ->get();
    }
}

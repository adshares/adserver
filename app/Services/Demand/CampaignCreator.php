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

namespace Adshares\Adserver\Services\Demand;

use Adshares\Ads\Util\AdsConverter;
use Adshares\Adserver\Http\Requests\Campaign\CampaignTargetingProcessor;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CampaignCreator
{
    public function __construct(private readonly ConfigurationRepository $configurationRepository)
    {
    }

    /**
     * @param array $input
     * @return Campaign
     */
    public function prepareCampaignFromInput(array $input): Campaign
    {
        foreach (['budget', 'date_start', 'medium', 'name', 'status'] as $field) {
            if (!array_key_exists($field, $input)) {
                throw new UnprocessableEntityHttpException(sprintf('Field `%s` is required', $field));
            }
        }

        $name = $input['name'];
        self::validateString($name, Campaign::NAME_MAXIMAL_LENGTH, 'name');
        $status = $input['status'];
        self::validateStatus($status);
        $landingUrl = $input['target_url'];
        self::validateString($landingUrl, Campaign::URL_MAXIMAL_LENGTH, 'targetUrl');
        self::validateUrl($landingUrl);
        $budget = $input['budget'];
        self::validateClickAmount($budget, 'budget');
        $maxCpc = $input['max_cpc'] ?? null;
        if (null !== $maxCpc) {
            self::validateClickAmount($maxCpc, 'maxCpc');
        }
        $maxCpm = $input['max_cpm'] ?? null;
        if (null !== $maxCpm) {
            self::validateClickAmount($maxCpm, 'maxCpm');
        }
        $timeStart = $input['date_start'];
        self::validateDate($timeStart, 'dateStart');
        $timeEnd = $input['date_end'] ?? null;
        if (null !== $timeEnd) {
            self::validateDate($timeEnd, 'dateEnd');
            self::validateDateRange($timeStart, $timeEnd);
        }

        $medium = $input['medium'];
        $vendor = $input['vendor'] ?? null;
        $mediumSchema = $this->configurationRepository->fetchMedium($medium, $vendor);
        $campaignTargetingProcessor = new CampaignTargetingProcessor($mediumSchema);
        $require = $campaignTargetingProcessor->processTargetingRequire($input['targeting']['requires'] ?? []);
        $exclude = $campaignTargetingProcessor->processTargetingExclude($input['targeting']['excludes'] ?? []);

        if (null === ($bidStrategy = BidStrategy::fetchDefault($medium, $vendor))) {
            Log::critical(sprintf('Bid strategy for (`%s`, `%s`) is missing', $medium, $vendor));
            throw new RuntimeException();
        }

        return new Campaign([
            'landing_url' => $landingUrl,
            'name' => $name,
            'status' => $status,
            'budget' => $budget,
            'max_cpc' => $maxCpc,
            'max_cpm' => $maxCpm,
            'medium' => $medium,
            'vendor' => $vendor,
            'targeting_requires' => $require,
            'targeting_excludes' => $exclude,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'bid_strategy_uuid' => $bidStrategy->uuid,
        ]);
    }

    public function updateCampaign(array $input, Campaign $campaign): Campaign
    {
        foreach (['max_cpc', 'max_cpm'] as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                if (null !== $value) {
                    self::validateClickAmount($value, $field);
                }
                $campaign->$field = $value;
            }
        }

        if (array_key_exists('name', $input)) {
            $value = $input['name'];
            self::validateString($value, Campaign::NAME_MAXIMAL_LENGTH, 'name');
            $campaign->name = $value;
        }

        if (array_key_exists('status', $input)) {
            $value = $input['status'];
            self::validateStatus($value);
            $campaign->status = $value;
        }

        if (array_key_exists('target_url', $input)) {
            $value = $input['target_url'];
            self::validateString($value, Campaign::URL_MAXIMAL_LENGTH, 'targetUrl');
            self::validateUrl($value);
            $campaign->landing_url = $value;
        }

        if (array_key_exists('budget', $input)) {
            $value = $input['budget'];
            self::validateClickAmount($value, 'budget');
            $campaign->budget = $value;
        }

        $checkDateRange = false;
        if (array_key_exists('date_start', $input)) {
            $value = $input['date_start'];
            self::validateDate($value, 'dateStart');
            $checkDateRange = true;
            $campaign->time_start = $value;
        }

        if (array_key_exists('date_end', $input)) {
            $value = $input['date_end'];
            if (null !== $value) {
                self::validateDate($value, 'dateEnd');
                $checkDateRange = true;
            }
            $campaign->time_end = $value;
        }

        if ($checkDateRange && null !== $campaign->time_end) {
            self::validateDateRange($campaign->time_start, $campaign->time_end);
        }

        if (array_key_exists('targeting', $input)) {
            $mediumSchema = $this->configurationRepository->fetchMedium($campaign->medium, $campaign->vendor);
            $campaignTargetingProcessor = new CampaignTargetingProcessor($mediumSchema);
            $campaign->targeting_requires =
                $campaignTargetingProcessor->processTargetingRequire($input['targeting']['requires'] ?? []);
            $campaign->targeting_excludes =
                $campaignTargetingProcessor->processTargetingExclude($input['targeting']['excludes'] ?? []);
        }

        if (array_key_exists('bid_strategy_uuid', $input)) {
            $value = $input['bid_strategy_uuid'];
            self::validateBidStrategyUuid($value);
            $campaign->bid_strategy_uuid = $value;
        }

        return $campaign;
    }

    private static function validateBidStrategyUuid(mixed $value): void
    {
        if (!Utils::isUuidValid($value)) {
            throw new UnprocessableEntityHttpException(
                'Field `bidStrategyUuid` must be a hexadecimal string of length 32'
            );
        }
    }

    private static function validateClickAmount(mixed $value, string $field): void
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Field `%s` must be an integer', $field));
        }
        if ($value < 0 || $value > AdsConverter::TOTAL_SUPPLY) {
            throw new InvalidArgumentException(
                sprintf('Field `%s` must be an amount in clicks', $field)
            );
        }
    }

    private static function validateDate(mixed $value, string $field): void
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                sprintf('Field `%s` must be a string in ISO 8601 format', $field)
            );
        }
        if (false === DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value)) {
            throw new InvalidArgumentException(sprintf('Field `%s` must be in ISO 8601 format', $field));
        }
    }

    private static function validateDateRange(string $timeStart, string $timeEnd): void
    {
        if (
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $timeStart)
            > DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $timeEnd)
        ) {
            throw new InvalidArgumentException('Field `dateEnd` must be later than `dateStart`');
        }
    }

    private static function validateStatus(mixed $status): void
    {
        if (!is_int($status)) {
            throw new InvalidArgumentException('Field `status` must be an integer');
        }
        if (!Campaign::isStatusAllowed($status)) {
            throw new InvalidArgumentException('Field `status` must be one of supported states');
        }
    }

    private static function validateString(mixed $value, int $maximalLength, string $field): void
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                sprintf('Field `%s` must be a string', $field)
            );
        }
        if ('' === $value) {
            throw new InvalidArgumentException(sprintf('Field `%s` must be a non-empty string', $field));
        }
        if (strlen($value) > $maximalLength) {
            throw new InvalidArgumentException(
                sprintf('Field `%s` must have at most %d characters', $field, $maximalLength)
            );
        }
    }

    private static function validateUrl(string $value): void
    {
        if (false === filter_var($value, FILTER_VALIDATE_URL)) {
            throw new UnprocessableEntityHttpException('Field `targetUrl` must be a url');
        }
    }
}

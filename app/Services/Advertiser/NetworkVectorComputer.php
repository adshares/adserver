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

namespace Adshares\Adserver\Services\Advertiser;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Utilities\PercentileComputer;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NetworkVectorComputer
{
    public const TOTAL = 'total';

    private const SAMPLES_COUNT = 1e4;

    private const KEY_COUNT = 'count';

    private const KEY_DATA = 'data';

    private const KEY_CPM_PERCENTILE_COMPUTER = 'computer';

    private const KEY_CPM_PERCENTILES = 'cpm';

    /** @var int */
    private $adserverId;

    /** @var string */
    private $binaryStringBase;

    /** @var int */
    private $binaryStringLength;

    /** @var array */
    private $categories;

    /** @var array */
    private $masks;

    /** @var PercentileComputer */
    private $totalCpmPercentileComputer;

    /** @var array */
    private $totalCpmPercentiles;

    public function __construct(int $adServerId)
    {
        $this->adserverId = $adServerId;

        $this->binaryStringBase = hex2bin('00');
        $this->masks = [
            hex2bin('80'),
            hex2bin('40'),
            hex2bin('20'),
            hex2bin('10'),
            hex2bin('08'),
            hex2bin('04'),
            hex2bin('02'),
            hex2bin('01'),
        ];
    }

    public function compute(DateTimeInterface $dateFrom, DateTimeInterface $dateTo): void
    {
        $this->categories = [];

        $events = $this->fetchEvents($dateFrom, $dateTo);
        $eventsAllCount = $this->fetchAllEventsCount($dateFrom, $dateTo);
        $this->binaryStringLength = (int)ceil(count($events) / 8);
        $this->totalCpmPercentileComputer = new PercentileComputer();

        foreach ($events as $index => $event) {
            $campaignId = $event->campaign_id;
            $userData = $event->user_data;
            $eventValue = (int)$event->paid_amount_currency;
            $domain = $event->site_domain;
            $size = $event->size;

            $this->totalCpmPercentileComputer->add($campaignId, $eventValue);

            $charIndex = (int)($index / 8);
            $bitIndex = $index % 8;

            $data = json_decode($userData, true);
            unset($data['site']);

            if ($domain) {
                $data['site']['domain'] = $domain;
            }

            $mapped = AbstractFilterMapper::generateNestedStructure($data);

            foreach ($mapped as $category => $values) {
                foreach ($values as $value) {
                    $this->addToCategories($category . ':' . $value, $charIndex, $bitIndex, $campaignId, $eventValue);
                }
            }

            $this->addToCategories('size:' . $size, $charIndex, $bitIndex, $campaignId, $eventValue);
        }

        $this->computeCpmPercentiles();
        $this->storeData($eventsAllCount);
    }

    private function fetchEvents(DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        DB::statement('SET @SAMPLES_COUNT := ?', [self::SAMPLES_COUNT]);
        self::setMysqlVariableEventsCount($dateFrom, $dateTo);
        DB::statement('SET @index := 0');

        return DB::select(
            <<<SQL
SELECT b.network_campaign_id    AS campaign_id,
       i.user_data              AS user_data,
       sub.paid_amount_currency AS paid_amount_currency,
       s.domain                 AS site_domain,
       b.size                   AS size
FROM (
         SELECT c.id                        AS id,
                c.network_impression_id     AS network_impression_id,
                c.banner_id                 AS banner_id,
                c.site_id                   AS site_id,
                SUM(p.paid_amount_currency) AS paid_amount_currency
         FROM network_cases c
                  JOIN network_case_payments p ON c.id = p.network_case_id
         WHERE c.created_at BETWEEN ? AND ?
         GROUP BY c.id
     ) sub
         JOIN network_impressions i ON sub.network_impression_id = i.id
         JOIN network_banners b ON sub.banner_id = b.uuid
         JOIN sites s ON sub.site_id = s.uuid
WHERE ((@index := @index + 1) * @SAMPLES_COUNT) % @EVENTS_COUNT < @SAMPLES_COUNT;
SQL
            ,
            [$dateFrom, $dateTo]
        );
    }

    private function fetchAllEventsCount(DateTimeInterface $dateFrom, DateTimeInterface $dateTo): int
    {
        $value = DB::selectOne('SELECT @EVENTS_COUNT AS value')->value;
        if (null !== $value) {
            return $value;
        }

        self::setMysqlVariableEventsCount($dateFrom, $dateTo);

        return DB::selectOne('SELECT @EVENTS_COUNT AS value')->value;
    }

    private function addToCategories(string $key, int $charIndex, int $bitIndex, int $campaignId, int $eventValue): void
    {
        if (isset($this->categories[$key])) {
            $this->categories[$key][self::KEY_COUNT]++;
        } else {
            $this->categories[$key][self::KEY_COUNT] = 1;
            $this->categories[$key][self::KEY_DATA] = str_repeat($this->binaryStringBase, $this->binaryStringLength);
            $this->categories[$key][self::KEY_CPM_PERCENTILE_COMPUTER] = new PercentileComputer();
        }

        $this->categories[$key][self::KEY_DATA][$charIndex] =
            $this->categories[$key][self::KEY_DATA][$charIndex] | $this->masks[$bitIndex];
        $this->categories[$key][self::KEY_CPM_PERCENTILE_COMPUTER]->add($campaignId, $eventValue);
    }

    private function computeCpmPercentiles(): void
    {
        $cpmPercentileRanks = [25, 50, 75];

        $this->totalCpmPercentiles = $this->totalCpmPercentileComputer->percentiles($cpmPercentileRanks);

        foreach ($this->categories as $category => $data) {
            $result = $data[self::KEY_CPM_PERCENTILE_COMPUTER]->percentiles($cpmPercentileRanks);
            $this->categories[$category][self::KEY_CPM_PERCENTILES] = array_merge($result, $this->totalCpmPercentiles);
        }
    }

    private function storeData(int $eventsAllCount): void
    {
        DB::beginTransaction();
        try {
            DB::table('network_vectors')->where('network_host_id', $this->adserverId)->delete();
            if ($this->binaryStringLength > 0) {
                DB::table('network_vectors')->insert(
                    [
                        'network_host_id' => $this->adserverId,
                        'key' => self::TOTAL,
                        'data' => str_repeat(hex2bin('FF'), $this->binaryStringLength),
                        'occurrences' => 8 * $this->binaryStringLength,
                        'cpm_25' => $this->totalCpmPercentiles[0],
                        'cpm_50' => $this->totalCpmPercentiles[1],
                        'cpm_75' => $this->totalCpmPercentiles[2],
                        'negation_cpm_25' => $this->totalCpmPercentiles[0],
                        'negation_cpm_50' => $this->totalCpmPercentiles[1],
                        'negation_cpm_75' => $this->totalCpmPercentiles[2],
                    ]
                );
            }
            foreach ($this->categories as $category => $data) {
                DB::table('network_vectors')->insert(
                    [
                        'network_host_id' => $this->adserverId,
                        'key' => $category,
                        'data' => $data[self::KEY_DATA],
                        'occurrences' => $data[self::KEY_COUNT],
                        'cpm_25' => $data[self::KEY_CPM_PERCENTILES][0],
                        'cpm_50' => $data[self::KEY_CPM_PERCENTILES][1],
                        'cpm_75' => $data[self::KEY_CPM_PERCENTILES][2],
                        'negation_cpm_25' => $data[self::KEY_CPM_PERCENTILES][3],
                        'negation_cpm_50' => $data[self::KEY_CPM_PERCENTILES][4],
                        'negation_cpm_75' => $data[self::KEY_CPM_PERCENTILES][5],
                    ]
                );
            }
            NetworkVectorsMeta::upsert($this->adserverId, $eventsAllCount);
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();

            Log::warning(sprintf('Exception during storing data (%s)', $throwable->getMessage()));
        }
    }

    private static function setMysqlVariableEventsCount(DateTimeInterface $dateFrom, DateTimeInterface $dateTo): void
    {
        DB::statement(
            <<<SQL
SET @EVENTS_COUNT := (
    SELECT COUNT(*)
    FROM (
             SELECT c.id                        AS id,
                    c.network_impression_id     AS network_impression_id,
                    c.banner_id                 AS banner_id,
                    SUM(p.paid_amount_currency) AS paid_amount_currency
             FROM network_cases c
                      JOIN network_case_payments p ON c.id = p.network_case_id
             WHERE c.created_at BETWEEN ? AND ?
             GROUP BY c.id
         ) sub
             JOIN network_impressions i on sub.network_impression_id = i.id
             JOIN network_banners b on sub.banner_id = b.uuid
)
SQL
            ,
            [$dateFrom, $dateTo]
        );
    }
}

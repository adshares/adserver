<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Services\Advertiser;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Utilities\Bucketer;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TargetingReachComputer
{
    public const SAMPLES_COUNT = 1e5;

    /** @var string */
    private $binaryStringBase;

    /** @var int */
    private $binaryStringLength;

    /** @var array */
    private $categories;

    /** @var array */
    private $masks;

    public function __construct()
    {
        $this->binaryStringBase = hex2bin('00');
        $this->binaryStringLength = (int)ceil(self::SAMPLES_COUNT / 8);
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

        foreach ($events as $index => $event) {
            $userData = $event->our_userdata;
            $domain = $event->domain;
            $eventValue = $event->event_value_currency;

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
                    $key = $category.':'.$value;
                    $this->addToCategories($key, $charIndex, $bitIndex, $eventValue);
                }
            }

            $key = 'size:'.$event->creative_size;
            $this->addToCategories($key, $charIndex, $bitIndex, $eventValue);
        }

        $this->computePercentiles();
        $this->storeData();

        //TODO remove logging
        Log::info('Categories: '.count($this->categories));
        foreach ($this->categories as $category => $value) {
            if (self::SAMPLES_COUNT <= 10) {
                Log::info(
                    sprintf(
                        '%s: %d : %s : %s',
                        str_pad(
                            base_convert(bin2hex($value['data']), 16, 2),
                            $this->binaryStringLength * 8,
                            '0',
                            STR_PAD_LEFT
                        ),
                        $value['count'],
                        $category,
                        json_encode($value['percentiles'])
                    )
                );
            } else {
                Log::info(
                    sprintf(
                        '%d : %s : %s',
                        $value['count'],
                        $category,
                        json_encode($value['percentiles'])
                    )
                );
            }
        }
    }

    private function fetchEvents(DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        DB::statement('SET @SAMPLES_COUNT := ?', [self::SAMPLES_COUNT]);
        DB::statement(
            'SET @EVENTS_COUNT := (SELECT COUNT(*) FROM event_logs el JOIN banners b on el.banner_id = b.uuid '
            .'WHERE el.created_at BETWEEN ? AND ? AND el.payment_id IS NOT NULL)',
            [$dateFrom, $dateTo]
        );
        DB::statement('SET @index := 0');

        return DB::select(
            <<<SQL
SELECT our_userdata, event_value_currency, domain, creative_size
FROM event_logs el
         JOIN banners b on el.banner_id = b.uuid
WHERE el.created_at BETWEEN ? AND ?
  AND el.payment_id IS NOT NULL
  AND ((@index := @index + 1) * @SAMPLES_COUNT) % @EVENTS_COUNT < @SAMPLES_COUNT
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

        DB::statement(
            'SET @EVENTS_COUNT := (SELECT COUNT(*) FROM event_logs el JOIN banners b on el.banner_id = b.uuid '
            .'WHERE el.created_at BETWEEN ? AND ? AND el.payment_id IS NOT NULL)',
            [$dateFrom, $dateTo]
        );

        return DB::selectOne('SELECT @EVENTS_COUNT AS value')->value;
    }

    private function addToCategories(string $key, int $charIndex, int $bitIndex, int $eventValue): void
    {
        if (isset($this->categories[$key])) {
            $this->categories[$key]['count']++;
        } else {
            $this->categories[$key]['count'] = 1;
            $this->categories[$key]['data'] = str_repeat($this->binaryStringBase, $this->binaryStringLength);
            $this->categories[$key]['bucketer'] = new Bucketer();
        }
        $this->categories[$key]['data'][$charIndex] =
            $this->categories[$key]['data'][$charIndex] | $this->masks[$bitIndex];
        $this->categories[$key]['bucketer']->add($eventValue);
    }

    private function computePercentiles(): void
    {
        $percentiles = [0.25, 0.5, 0.75];

        foreach ($this->categories as $category => $data) {
            $result = $data['bucketer']->percentiles($percentiles);
            $this->categories[$category]['percentiles'] = [
                '25%' => $result[0] * 1e3,
                '50%' => $result[1] * 1e3,
                '75%' => $result[2] * 1e3,
            ];
        }
    }

    private function storeData(): void
    {
        DB::beginTransaction();
        try {
            DB::table('targeting_reach')->delete();
            foreach ($this->categories as $category => $data) {
                DB::table('targeting_reach')->insert(
                    [
                        'key' => $category,
                        'data' => $data['data'],
                        'occurrences' => $data['count'],
                        'percentile_25' => $data['percentiles']['25%'],
                        'percentile_50' => $data['percentiles']['50%'],
                        'percentile_75' => $data['percentiles']['75%'],
                    ]
                );
            }
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();

            Log::warning(sprintf('Exception during storing data (%s)', $throwable->getMessage()));
        }
    }
}

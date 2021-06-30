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

namespace Adshares\Adserver\Repository\Common;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

use function implode;
use function sprintf;
use function str_replace;

abstract class MySqlQueryBuilder
{
    private const CONDITION_AND_TYPE = 'AND';
    private const CONDITION_OR_TYPE = 'OR';

    private const QUERY = 'SELECT #columns FROM #tableName #join #where #groupBy #having';

    protected $query = '';
    protected $selectedColumns = [];
    protected $whereConditions = [];
    protected $groupByColumns = [];
    protected $havingConditions = [];
    protected $joins = [];

    /** @var string */
    private $type;

    public function __construct(string $type)
    {
        if (!$this->isTypeAllowed($type)) {
            throw new RuntimeException(sprintf('Unsupported query type: %s', $type));
        }

        $this->setType($type);
        $this->query = str_replace('#tableName', $this->getTableName(), self::QUERY);
    }

    protected function where(string $condition, string $type = self::CONDITION_AND_TYPE): void
    {
        if (count($this->whereConditions) > 0) {
            $this->whereConditions[] = sprintf('%s %s', $type, $condition);
        } else {
            $this->whereConditions[] = $condition;
        }
    }

    protected function groupBy(string $groupByItem): void
    {
        $this->groupByColumns[] = $groupByItem;
    }

    protected function having(string $conditionItem, ?string $type = self::CONDITION_OR_TYPE): void
    {
        if (count($this->havingConditions) > 0) {
            $this->havingConditions[] = sprintf('%s %s', $type, $conditionItem);
        } else {
            $this->havingConditions[] = $conditionItem;
        }
    }

    protected function join(string $tableName, string $condition, string $type = 'INNER'): void
    {

        $this->joins[] = sprintf('%s JOIN %s ON %s', $type, $tableName, $condition);
    }

    protected function column(string $column): void
    {
        $this->selectedColumns[] = $column;
    }

    public function build(): string
    {
        $additional = implode(',', $this->selectedColumns);
        $replacement = $additional ?: '';
        $this->query = str_replace('#columns', $replacement, $this->query);

        $additional = implode(' ', $this->whereConditions);
        $replacement = $additional ? 'WHERE ' . $additional : '';
        $this->query = str_replace('#where', $replacement, $this->query);

        $additional = implode(',', $this->groupByColumns);
        $replacement = $additional ? 'GROUP BY ' . $additional : '';
        $this->query = str_replace('#groupBy', $replacement, $this->query);

        $additional = implode(' ', $this->havingConditions);
        $replacement = $additional ? 'HAVING ' . $additional : '';
        $this->query = str_replace('#having', $replacement, $this->query);

        $additional = implode(' ', $this->joins);
        $replacement = $additional ?: '';
        $this->query = str_replace('#join', $replacement, $this->query);


        return $this->query;
    }

    protected function getType(): string
    {
        return $this->type;
    }

    private function setType(string $type): void
    {
        $this->type = $type;
    }

    public static function convertDateTimeToMySqlDate(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    public static function convertMySqlDateToDateTime(string $mysqlDate, DateTimeZone $dateTimeZone = null): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $mysqlDate, $dateTimeZone);
    }

    abstract protected function isTypeAllowed(string $type): bool;

    abstract protected function getTableName(): string;
}

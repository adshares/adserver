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

namespace Adshares\Adserver\Repository\Common;

use RuntimeException;
use function implode;
use function sprintf;
use function str_replace;

abstract class MySqlQueryBuilder
{
    private const CONDITION_AND_TYPE = 'AND';
    private const QUERY = 'SELECT #columns FROM #tableName #where #groupBy #having';

    protected $query = '';
    protected $selectedColumns = [];
    protected $whereConditions = [];
    protected $groupByColumns = [];
    protected $havingConditions = [];

    public function __construct(string $type)
    {
        if (!$this->isTypeAllowed($type)) {
            throw new RuntimeException(sprintf('Unsupported query type: %s', $type));
        }

        $this->query = str_replace('#tableName', $this->getTableName(), self::QUERY);
    }

    protected function where(string $condition, ?string $type = self::CONDITION_AND_TYPE): void
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

    protected function having(string $conditionItem): void
    {
        $this->havingConditions[] = $conditionItem;
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
        $replacement = $additional ? 'WHERE '.$additional : '';
        $this->query = str_replace('#where', $replacement, $this->query);

        $additional = implode(',', $this->groupByColumns);
        $replacement = $additional ? 'GROUP BY '.$additional : '';
        $this->query = str_replace('#groupBy', $replacement, $this->query);

        $additional = implode(',', $this->havingConditions);
        $replacement = $additional ? 'HAVING '.$additional : '';

        $this->query = str_replace('#having', $replacement, $this->query);

        return $this->query;
    }

    abstract protected function isTypeAllowed(string $type): bool;

    abstract protected function getTableName(): string;
}

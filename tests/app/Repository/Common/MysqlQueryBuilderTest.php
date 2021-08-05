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

namespace Adshares\Adserver\Tests\Repository\Common;

use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use PHPUnit\Framework\TestCase;

final class MysqlQueryBuilderTest extends TestCase
{
    protected $mysqlBuilder;

    protected function setUp(): void
    {
        $type = 'type';

        /** @var MySqlQueryBuilder mysqlBuilder */
        $class = new class ($type) extends MySqlQueryBuilder {
            public function run()
            {
                $this->column('a');
                $this->column('b');
                $this->column('c');
                $this->where('t.a > 1');
                $this->where('t.b < 10');
                $this->groupBy('t.c');
                $this->having('t.c');
            }

            protected function isTypeAllowed(string $type): bool
            {
                return true;
            }

            protected function getTableName(): string
            {
                return 'table_name t';
            }
        };

        $this->mysqlBuilder = $class;
    }

    public function testQueryBuilder(): void
    {
        $this->mysqlBuilder->run();
        $query = $this->mysqlBuilder->build();

        $expected = 'SELECT a,b,c FROM table_name t  WHERE t.a > 1 AND t.b < 10 GROUP BY t.c HAVING t.c';
        $this->assertEquals($expected, $query);
    }
}

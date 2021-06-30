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

namespace Adshares\Adserver\Facades;

use Illuminate\Support\Facades\DB as BaseDB;

class DB extends BaseDB
{
    /**
     * Determine if a database driver is MySql.
     *
     * @return bool
     */
    public static function isMySql()
    {
        return 'mysql' === self::getDbDriver();
    }

    /**
     * Get database driver name.
     *
     * @return string
     */
    protected static function getDbDriver()
    {
        $connection = config('database.default');

        return config("database.connections.{$connection}.driver");
    }

    /**
     * Determine if a database driver is PostgreSQL.
     *
     * @return bool
     */
    public static function isPostgres()
    {
        return 'pgsql' === self::getDbDriver();
    }

    /**
     * Determine if a database driver is SQLite.
     *
     * @return bool
     */
    public static function isSQLite()
    {
        return 'sqlite' === self::getDbDriver();
    }

    /**
     * Determine if a database driver is SQL Server.
     *
     * @return bool
     */
    public static function isSqlServer()
    {
        return 'sqlsrv' === self::getDbDriver();
    }
}

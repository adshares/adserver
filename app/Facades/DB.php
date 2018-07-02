<?php

namespace Adshares\Adserver\Facades;

use Illuminate\Support\Facades\DB as BaseDB;

class DB extends BaseDB
{
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
     * Determine if a database driver is MySql.
     *
     * @return bool
     */
    public static function isMySql()
    {
        return 'mysql' === self::getDbDriver();
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

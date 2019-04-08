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

$limit = 1000;

$databaseHost = getenv('DB_HOST');
$databasePort = getenv('DB_PORT') ?? 3306;
$databaseName = getenv('DB_DATABASE');
$databaseUsername = getenv('DB_USERNAME');
$databasePassword = getenv('DB_PASSWORD');

$rows = null;
$querySql =
    'SELECT id, our_context, their_context, domain from event_logs 
    WHERE event_type in (\'click\', \'view\') AND domain IS NULL LIMIT %d OFFSET %d';

$updateSql = 'UPDATE event_logs SET domain=? WHERE id=?';
$dsn = sprintf('mysql:host=%s;dbname=%s', $databaseHost, $databaseName);

$dbh = new PDO($dsn, $databaseUsername, $databasePassword);
$i = 1;
do {
    $dbh->beginTransaction();
    try {
        $sth = $dbh->query(sprintf($querySql, $limit, 0));
        $rows = $sth->fetchAll();

        print("LIMIT: ".$limit."\t PACK: ".$i++."\n");

        foreach ($rows as $row) {
            $id = $row['id'];
            $domain = null;

            if ($row['our_context']) {
                $context = json_decode($row['our_context'], true);

                if (!is_array($context)) {
                    $context = json_decode($context, true);
                }

                if (isset($context['url'])) {
                    $domain = parse_url($context['url'], PHP_URL_HOST);
                }
            } else {
                $context = json_decode($row['their_context'], true);

                if (isset($context['site']['domain'])) {
                    $domain = $context['site']['domain'];
                }
            }

            $dbh->prepare($updateSql)->execute([$domain, $id]);
        }
        $dbh->commit();
    } catch (Exception $exception) {
        $dbh->rollBack();
        print($exception->getMessage());
    }
} while (count($rows) === $limit);

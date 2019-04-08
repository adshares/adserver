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



$updateSqlNetworkEventLogs = 'update network_event_logs nel 
    SET domain = (
    select SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(landing_url, \'/\', 3), \'://\', -1), \'/\', 1), \'?\', 1)
    from network_campaigns nc
             join network_banners nb on nc.id = nb.network_campaign_id
    where nb.uuid = nel.banner_id
)
where nel.domain is null;
';


$rows = null;
$querySqlEventLogs =
    'SELECT id, our_context, their_context, domain from event_logs 
    WHERE event_type in (\'click\', \'view\') AND domain IS NULL LIMIT %d OFFSET %d';

$updateSqlEventLogs = 'UPDATE event_logs SET domain=? WHERE id=?';
$dsn = sprintf('mysql:host=%s;dbname=%s', $databaseHost, $databaseName);

try {
    $dbh = new PDO($dsn, $databaseUsername, $databasePassword);
} catch (PDOException $exception) {
    print($exception->getMessage()."\n");
    exit;
}



print("Updating `network_event_logs`\n");
$dbh->prepare($updateSqlNetworkEventLogs)->execute();
print("`network_event_logs` has been updated\n\n");

print("Updating `event_logs`\n");
$i = 1;
do {
    $dbh->beginTransaction();
    try {
        $sth = $dbh->query(sprintf($querySqlEventLogs, $limit, 0));
        $rows = $sth->fetchAll();

        if (count($rows) === 0) {
            print("Nothing to do.\n");
            break;
        }

        print("LIMIT: $limit \t PACK: $i \n");
        $i++;

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

            $dbh->prepare($updateSqlEventLogs)->execute([$domain, $id]);
        }
        $dbh->commit();
    } catch (Exception $exception) {
        $dbh->rollBack();
        print($exception->getMessage());
    }
} while (count($rows) === $limit);
print("`event_logs` has been updated\n\n");

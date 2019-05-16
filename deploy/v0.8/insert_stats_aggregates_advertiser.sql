INSERT INTO event_logs_hourly (`clicks`, `views`, `cost`, `clicks_all`, `views_all`, `views_unique`, `domain`,
                               `campaign_id`, `banner_id`, `advertiser_id`, `hour_timestamp`)
SELECT clicks,
       views,
       cost,
       clicks_all,
       views_all,
       views_unique,
       domain,
       campaign_id,
       banner_id,
       advertiser_id,
       CONCAT(y, '-', LPAD(m, 2, '0'), '-', LPAD(d, 2, '0'), ' ', LPAD(h, 2, '0'), ':00:00') AS hour_timestamp
FROM
  (
    SELECT SUM(IF(
          e.event_type = 'view' AND e.is_view_clicked = 1 AND e.event_value_currency IS NOT NULL AND e.reason = 0,
          1, 0))                                                                                        AS clicks,
           SUM(IF(e.event_type = 'view' AND e.event_value_currency IS NOT NULL AND e.reason = 0, 1, 0)) AS views,
           SUM(IF(e.event_type IN ('click', 'view') AND e.event_value_currency IS NOT NULL AND e.reason = 0,
                  e.event_value_currency, 0))                                                           AS cost,
           SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1, 1, 0))                               AS clicks_all,
           SUM(IF(e.event_type = 'view', 1, 0))                                                         AS views_all,
           COUNT(DISTINCT (CASE
                             WHEN e.event_type = 'view' AND e.event_value_currency IS NOT NULL AND e.reason = 0
                               THEN e.user_id END))                                                     AS views_unique,
           IFNULL(e.domain, '')                                                                         AS domain,
           e.campaign_id                                                                                AS campaign_id,
           e.banner_id                                                                                  AS banner_id,
           e.advertiser_id                                                                              AS advertiser_id,
           YEAR(e.created_at)                                                                           AS y,
           MONTH(e.created_at)                                                                          AS m,
           DAY(e.created_at)                                                                            AS d,
           HOUR(e.created_at)                                                                           AS h
    FROM (SELECT * FROM event_logs WHERE event_type IN ('click', 'view')) AS e
           INNER JOIN campaigns c ON c.uuid = e.campaign_id
    WHERE c.deleted_at is null
      AND e.created_at BETWEEN (SELECT created_at AS date_start
                                FROM event_logs
                                ORDER BY created_at ASC
                                LIMIT 1) AND (SELECT DATE_SUB(DATE_SUB(s.now, INTERVAL SECOND(s.now) + 1 SECOND),
                                                              INTERVAL MINUTE(s.now) MINUTE) AS date_end
                                              FROM
                                                  (SELECT NOW() AS now) AS s)
    GROUP BY IFNULL(e.domain, ''),e.campaign_id,e.banner_id,e.advertiser_id,
             YEAR(e.created_at), MONTH(e.created_at), DAY(e.created_at), HOUR(e.created_at)
    HAVING clicks > 0
        OR views > 0
        OR cost > 0
        OR clicks_all > 0
        OR views_all > 0
        OR views_unique > 0
  ) AS stats
ORDER BY hour_timestamp;

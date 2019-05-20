In this version two tables were added to store statistics aggregated per hour.

New events will be processed on-line by command executed by cron.
Data stored to the time **must be** processed manually.
To ease this task two scripts were prepared.
Each of them fills one of the new tables with aggregated statistics data:
- script `insert_stats_aggregates_advertiser.sql` inserts data for advertiser.
- script `insert_stats_aggregates_publisher.sql` inserts data for publisher.

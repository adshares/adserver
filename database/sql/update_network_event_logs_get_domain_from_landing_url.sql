update network_event_logs nel SET domain = (
    select SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(landing_url, '/', 3), '://', -1), '/', 1), '?', 1)
    from network_campaigns nc
    join network_banners nb on nc.id = nb.network_campaign_id
    where nb.uuid = nel.banner_id
)
where nel.domain is null;

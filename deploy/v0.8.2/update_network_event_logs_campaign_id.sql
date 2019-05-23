update network_event_logs nel set campaign_id=(
    select nc.uuid from network_banners nb join network_campaigns nc on nc.id=nb.network_campaign_id where nb.uuid=nel.banner_id
);

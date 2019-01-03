#!/usr/bin/env bash

if [ ! -e /tmp/ads_fetch_hosts_in_progress ]; then
    touch /tmp/ads_fetch_hosts_in_progress

    ./artisan ads:fetch-hosts --quiet

    rm -f /tmp/ads_fetch_hosts_in_progress
fi

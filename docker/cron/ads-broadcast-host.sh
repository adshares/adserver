#!/usr/bin/env bash

if [ ! -e /tmp/ads_broadcast_host_in_progress ]; then
    touch /tmp/ads_broadcast_host_in_progress

    ./artisan ads:broadcast-host

    rm -f /tmp/ads_broadcast_host_in_progress
fi

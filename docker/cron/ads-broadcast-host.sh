#!/usr/bin/env bash

if [ ! -e ads_broadcast_host_in_progress ]; then
    touch ads_broadcast_host_in_progress

    ./artisan ads:broadcast-host

    rm -f ads_broadcast_host_in_progress
fi

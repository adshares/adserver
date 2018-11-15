#!/usr/bin/env bash

if [ ! -e ads_broadcast_host ]; then
    touch ads_broadcast_host

    ./artisan ads:broadcast-host

    rm -f ads_broadcast_host
fi

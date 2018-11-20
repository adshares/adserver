#!/usr/bin/env bash

if [ ! -e ads_fetch_hosts_in_progress ]; then
    touch ads_fetch_hosts_in_progress

    ./artisan ads:fetch-hosts

    rm -f ads_fetch_hosts_in_progress
fi

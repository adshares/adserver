#!/usr/bin/env bash

if [ ! -e ads_fetch_hosts ]; then
    touch ads_fetch_hosts

    ./artisan ads:fetch-hosts

    rm -f ads_fetch_hosts
fi

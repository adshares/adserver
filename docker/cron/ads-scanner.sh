#!/usr/bin/env bash

if [ ! -e ads_scanner_in_progress ]; then
    touch ads_scanner_in_progress

    ./artisan ads:get-tx-in
    ./artisan ads:process-tx

    rm -f ads_scanner_in_progress
fi

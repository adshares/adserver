#!/usr/bin/env bash

if [ ! -e /tmp/ads_scanner_in_progress ]; then
    touch /tmp/ads_scanner_in_progress

    ./artisan ads:get-tx-in
    ./artisan ads:process-tx
    ./artisan ops:supply:payments:send
    ./artisan ops:adselect:payment:export

    rm -f /tmp/ads_scanner_in_progress
fi

#!/usr/bin/env bash

if [ ! -e /tmp/ads_scanner_in_progress ]; then
    touch /tmp/ads_scanner_in_progress

    ./artisan ads:process-tx
    ./artisan ops:supply:payments:process
    ./artisan ops:adselect:case-payments:export
    ./artisan ops:stats:aggregate:publisher

    rm -f /tmp/ads_scanner_in_progress
fi

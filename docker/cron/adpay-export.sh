#!/usr/bin/env bash

if [ ! -e /tmp/adpay_export_in_progress ]; then
    touch /tmp/adpay_export_in_progress

    ./artisan ops:adpay:campaign:export
    ./artisan ops:adpay:event:export

    rm -f /tmp/adpay_export_in_progress
fi

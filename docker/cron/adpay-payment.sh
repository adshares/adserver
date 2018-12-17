#!/usr/bin/env bash

set -e

if [ ! -e adpay_export_in_progress ]; then
    touch adpay_export_in_progress

    ./artisan ops:adpay:campaign:export
    ./artisan ops:adpay:event:export

    rm -f adpay_export_in_progress
fi

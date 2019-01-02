#!/usr/bin/env bash

if [ ! -e /tmp/ops_payments_in_progress ]; then
    touch /tmp/ops_payments_in_progress

    ./artisan ops:adpay:payments:get
    ./artisan ops:demand:payments:prepare
    ./artisan ops:demand:payments:send

    rm -f /tmp/ops_payments_in_progress
fi

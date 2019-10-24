#!/usr/bin/env bash

if [ ! -e /tmp/ops_payments_in_progress ]; then
    touch /tmp/ops_payments_in_progress

    ./artisan ops:demand:payments:process

    rm -f /tmp/ops_payments_in_progress
fi

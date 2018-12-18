#!/usr/bin/env bash

set -e

if [ ! -e ops_payments_in_progress ]
then
    touch ops_payments_in_progress

    ./artisan ops:adpay:payments:get
    ./artisan ops:demand:payments:prepare

    rm -f ops_payments_in_progress
fi

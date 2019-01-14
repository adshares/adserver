#!/usr/bin/env bash

if ! [[ "$1" == "" ]]
then
    ADPAY_PAYMENTS_GET_PARAM="--sub=$1"
fi

if [ ! -e /tmp/ops_payments_in_progress ]; then
    touch /tmp/ops_payments_in_progress

    ./artisan ops:adpay:payments:get ${ADPAY_PAYMENTS_GET_PARAM}
    ./artisan ops:demand:payments:prepare
    ./artisan ops:demand:payments:send

    rm -f /tmp/ops_payments_in_progress
fi

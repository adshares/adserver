#!/usr/bin/env bash

ADPAY_PAYMENTS_GET_PARAM=""

while [[ "$1" != "" ]]
do
    case "$1" in
        --sub | -s )
            ADPAY_PAYMENTS_GET_PARAM="--sub=$2"
            shift
        ;;
        --timestamp | -t )
            ADPAY_PAYMENTS_GET_PARAM="--ts=$2"
            shift
        ;;
    esac
    shift
done

if [ ! -e /tmp/ops_payments_in_progress ]; then
    touch /tmp/ops_payments_in_progress

    ./artisan ops:adpay:payments:get ${ADPAY_PAYMENTS_GET_PARAM}
    ./artisan ops:demand:payments:prepare
    ./artisan ops:demand:payments:send

    rm -f /tmp/ops_payments_in_progress
fi

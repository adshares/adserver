#!/usr/bin/env bash
set -e

HERE=$(dirname $(dirname $(readlink -f "$0")))
source ${HERE}/_functions.sh root

SKIP_BROADCAST=1
SKIP_HOST_FETCHING=1
SKIP_COLD_WALLET=1

TEMP_FILE="$(mktemp).txt"

{
test ${SKIP_BROADCAST:-0} -eq 0 && \
    echo "0    */12 * * * php ${PWD}/artisan ads:broadcast-host            &> /dev/null"

test ${SKIP_HOST_FETCHING:-0} -eq 0 && \
    echo "30   */6  * * * php ${PWD}/artisan ads:fetch-hosts               &> /dev/null"

    echo "*    *    * * * php ${PWD}/artisan ops:demand:inventory:import   &> /dev/null"
    echo "*    *    * * * php ${PWD}/artisan ops:adselect:inventory:export &> /dev/null"

    echo "*    *    * * * php ${PWD}/artisan ops:adpay:campaign:export     &> /dev/null"
    echo "*    *    * * * php ${PWD}/artisan ops:adpay:event:export        &> /dev/null"

    echo "15   *    * * * php ${PWD}/artisan ops:adpay:payments:get        &> /dev/null"
    echo "15   *    * * * php ${PWD}/artisan ops:demand:payments:prepare   &> /dev/null"
    echo "15   *    * * * php ${PWD}/artisan ops:demand:payments:send      &> /dev/null"

    echo "59   *    * * * php ${PWD}/artisan ops:demand:payments:block     &> /dev/null"

    echo "*/8  *    * * * php ${PWD}/artisan ads:get-tx-in                 &> /dev/null"
    echo "*/8  *    * * * php ${PWD}/artisan ads:process-tx                &> /dev/null"
    echo "*/8  *    * * * php ${PWD}/artisan ops:supply:payments:send      &> /dev/null"
    echo "*/8  *    * * * php ${PWD}/artisan ops:adselect:payment:export   &> /dev/null"

test ${SKIP_COLD_WALLET:-0} -eq 0 && \
    {
        echo "*/30 *    * * * php ${PWD}/artisan ops:wallet:transfer:cold      &> /dev/null"
        echo "*/30 *    * * * php ${PWD}/artisan ops:wallet:transfer:check     &> /dev/null"
    }
} | tee ${TEMP_FILE}

crontab -u ${VENDOR_USER} ${TEMP_FILE}

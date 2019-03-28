#!/usr/bin/env bash
set -eu

SERVICE_DIR=${SERVICE_DIR:-$(dirname $(dirname $(readlink -f $0)))}

echo -n "*    *    * * * php ${SERVICE_DIR}/artisan ops:demand:inventory:import &> /dev/null &&"
echo "*    *    * * * php ${SERVICE_DIR}/artisan ops:adselect:inventory:export &> /dev/null"

echo -n "*    *    * * * php ${SERVICE_DIR}/artisan ops:adpay:campaign:export &> /dev/null &&"
echo "*    *    * * * php ${SERVICE_DIR}/artisan ops:adpay:event:export &> /dev/null"

echo -n "15   *    * * * php ${SERVICE_DIR}/artisan ops:adpay:payments:get &> /dev/null &&"
echo -n "15   *    * * * php ${SERVICE_DIR}/artisan ops:demand:payments:prepare &> /dev/null &&"
echo "15   *    * * * php ${SERVICE_DIR}/artisan ops:demand:payments:send &> /dev/null"

echo "59   *    * * * php ${SERVICE_DIR}/artisan ops:demand:payments:block &> /dev/null"

echo -n "*/8  *    * * * php ${SERVICE_DIR}/artisan ads:get-tx-in &> /dev/null &&"
echo -n "*/8  *    * * * php ${SERVICE_DIR}/artisan ads:process-tx &> /dev/null &&"
echo -n "*/8  *    * * * php ${SERVICE_DIR}/artisan ops:supply:payments:send &> /dev/null &&"
echo "*/8  *    * * * php ${SERVICE_DIR}/artisan ops:adselect:payment:export &> /dev/null"

echo "0    0    * * * php ${SERVICE_DIR}/artisan ops:license:fetch &> /dev/null"

test ${SKIP_COLD_WALLET:-0} -eq 0 && \
{
    echo -n "*/30 *    * * * php ${SERVICE_DIR}/artisan ops:wallet:transfer:cold &> /dev/null &&"
    echo "*/30 *    * * * php ${SERVICE_DIR}/artisan ops:wallet:transfer:check &> /dev/null"
}

test ${SKIP_BROADCAST:-0} -eq 0 && \
    echo "0    */12 * * * php ${SERVICE_DIR}/artisan ads:broadcast-host &> /dev/null"

test ${SKIP_HOST_FETCHING:-0} -eq 0 && \
    echo "30   */6  * * * php ${SERVICE_DIR}/artisan ads:fetch-hosts &> /dev/null"

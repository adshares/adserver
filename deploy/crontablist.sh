#!/usr/bin/env bash
set -eu

SERVICE_DIR=${SERVICE_DIR:-$(dirname $(dirname $(readlink -f $0)))}

echo -n "* * * * *"
echo -n "php ${SERVICE_DIR}/artisan ops:demand:inventory:import &> /dev/null && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:inventory:export &> /dev/null"
echo ""

echo -n "* * * * *"
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:campaign:export &> /dev/null && "
echo "php ${SERVICE_DIR}/artisan ops:adpay:event:export &> /dev/null"

echo -n "15 * * * *"
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:payments:get &> /dev/null && "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:prepare &> /dev/null && "
echo "php ${SERVICE_DIR}/artisan ops:demand:payments:send &> /dev/null"

echo "59 * * * *"
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:block &> /dev/null"

echo -n "*/8 * * * *"
echo -n "php ${SERVICE_DIR}/artisan ads:get-tx-in &> /dev/null && "
echo -n "php ${SERVICE_DIR}/artisan ads:process-tx &> /dev/null && "
echo -n "php ${SERVICE_DIR}/artisan ops:supply:payments:send &> /dev/null && "
echo "php ${SERVICE_DIR}/artisan ops:adselect:payment:export &> /dev/null"

echo "0 0 * * *"
echo -n "php ${SERVICE_DIR}/artisan ops:license:fetch &> /dev/null"

test ${SKIP_COLD_WALLET:-0} -eq 0 && \
{
    echo -n "*/30 *    * * *"
    echo -n "php ${SERVICE_DIR}/artisan ops:wallet:transfer:cold &> /dev/null && "
    echo "php ${SERVICE_DIR}/artisan ops:wallet:transfer:check &> /dev/null"
}

test ${SKIP_BROADCAST:-0} -eq 0 && \
{
    echo "0 */12 * * *"
    echo -n "php ${SERVICE_DIR}/artisan ads:broadcast-host &> /dev/null"
}

test ${SKIP_HOST_FETCHING:-0} -eq 0 && \
{
    echo "30 */6  * * *"
    echo -n "php ${SERVICE_DIR}/artisan ads:fetch-hosts &> /dev/null"
}

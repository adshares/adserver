#!/usr/bin/env bash
set -eu

SERVICE_DIR=${SERVICE_DIR:-$(dirname $(dirname $(readlink -f $0)))}
LOG_DIR=${LOG_DIR:-""}

if [[ -z ${LOG_DIR} ]]
then
    _REDIRECTION="&> /dev/null"
else
    _REDIRECTION="&>> ${LOG_DIR}/adserver-crontab.log"
fi

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:inventory:import &> ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:inventory:export &> ${_REDIRECTION}"
echo ""

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:campaign:export &> ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:event:export &> ${_REDIRECTION}"
echo ""

echo -n "15 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:payments:get &> ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:prepare &> ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:send &> ${_REDIRECTION}"
echo ""

echo -n "59 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:block &> ${_REDIRECTION}"
echo ""

echo -n "*/8 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ads:get-tx-in &> ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ads:process-tx &> ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:supply:payments:send &> ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:payment:export &> ${_REDIRECTION}"
echo ""

echo -n "0 0 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:license:fetch &> ${_REDIRECTION}"
echo ""

test ${SKIP_COLD_WALLET:-0} -eq 0 && \
{
    echo -n "*/30 * * * * "
    echo -n "php ${SERVICE_DIR}/artisan ops:wallet:transfer:cold &> ${_REDIRECTION}"
    echo -n " && "
    echo -n "php ${SERVICE_DIR}/artisan ops:wallet:transfer:check &> ${_REDIRECTION}"
    echo ""
}

test ${SKIP_BROADCAST:-0} -eq 0 && \
{
    echo -n "0 */12 * * * "
    echo -n "php ${SERVICE_DIR}/artisan ads:broadcast-host &> ${_REDIRECTION}"
    echo ""
}

test ${SKIP_HOST_FETCHING:-0} -eq 0 && \
{
    echo -n "30 */6  * * * "
    echo -n "php ${SERVICE_DIR}/artisan ads:fetch-hosts &> ${_REDIRECTION}"
    echo ""
}

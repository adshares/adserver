#!/usr/bin/env bash
set -eu

SERVICE_DIR=${SERVICE_DIR:-$(dirname $(dirname $(readlink -f $0)))}
SERVICE_NAME=$(basename ${SERVICE_DIR})
BACKUP_DIR=$(dirname ${SERVICE_DIR})/.backup

LOG_DIR=${LOG_DIR:-""}

if [[ -z ${LOG_DIR} ]]
then
    _REDIRECTION=">/dev/null"
else
    _REDIRECTION="&>> ${LOG_DIR}/adserver-crontab.log"
fi

echo -n "0 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:exchange-rate:fetch"
echo ""

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:inventory:import ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:inventory:export ${_REDIRECTION}"
echo ""

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:event:export ${_REDIRECTION}"
echo ""

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:campaign:export ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:event:export ${_REDIRECTION}"
echo ""

echo -n "30 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:payments:get"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:prepare"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:send"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:stats:aggregate:advertiser"
echo ""

echo -n "59 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:block"
echo ""

echo -n "*/8 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ads:get-tx-in"
echo -n " && "
#echo -n "php ${SERVICE_DIR}/artisan ads:process-tx"
#echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:payment:export"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:stats:aggregate:publisher"
echo ""

echo -n "0 0 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:license:fetch"
echo ""

test ${SKIP_COLD_WALLET:-0} -eq 0 && \
{
    echo -n "*/30 * * * * "
    echo -n "php ${SERVICE_DIR}/artisan ops:wallet:transfer:cold"
    echo -n " && "
    echo -n "php ${SERVICE_DIR}/artisan ops:wallet:transfer:check"
    echo ""
}

test ${SKIP_BROADCAST:-0} -eq 0 && \
{
    echo -n "0 */12 * * * "
    echo -n "php ${SERVICE_DIR}/artisan ads:broadcast-host"
    echo ""
}

test ${SKIP_HOST_FETCHING:-0} -eq 0 && \
{
    echo -n "30 */6  * * * "
    echo -n "php ${SERVICE_DIR}/artisan ads:fetch-hosts"
    echo ""
}

echo -n "15 1 * * * "
echo -n "${SERVICE_DIR}/bin/archive_events.sh"
echo ""


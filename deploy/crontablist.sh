#!/usr/bin/env bash
set -eu

SERVICE_DIR=${SERVICE_DIR:-$(dirname $(dirname $(readlink -f $0)))}
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
echo -n "php ${SERVICE_DIR}/artisan ads:process-tx"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:supply:payments:send"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:payment:export"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:stats:aggregate:publisher"
echo ""

echo -n "0 0 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:license:fetch"
echo ""

env

_DB="${VENDOR_NAME}_${SERVICE}"
_TABLE="network_event_logs"
_CONDITION="created_at < CURRENT_DATE - INTERVAL 32 DAY"
_FILE="${BACKUP_DIR}/${_TABLE}-\$(date -u -Iseconds).sql"

echo -n "30 0 * * * "
echo -n "mysqldump --user ${VENDOR_USER} --password ${VENDOR_USER} --no-tablespaces --no-create-db --no-create-info --where=\"${_CONDITION}\" --result-file=${_FILE} ${_DB} ${_TABLE}"
echo -n " && "
echo -n "mysql --user ${VENDOR_USER} --password ${VENDOR_USER} --execute=\"DELETE FROM ${_TABLE} WHERE ${_CONDITION}\"" ${_DB}
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


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

echo -n "*/10 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:inventory:import ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:inventory:export ${_REDIRECTION}"
echo ""

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:case:export ${_REDIRECTION}"
echo ""

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:campaign:export ${_REDIRECTION}"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adpay:event:export ${_REDIRECTION}"
echo ""

echo -n "* * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:expired-withdrawal:cancel ${_REDIRECTION}"
echo ""

echo -n "10-20,*/5 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:process"
echo ""

echo -n "59 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:payments:block"
echo ""

echo -n "1-59/5 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ads:process-tx"
echo ""

echo -n "1-59/12 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:supply:payments:process"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:adselect:case-payments:export"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:stats:aggregate:publisher"
echo ""

echo -n "*/5 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:classification:request"
echo ""

echo -n "0-45/5,55 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:supply:site-rank:update"
echo ""

echo -n "50 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:supply:site-rank:update --all"
echo ""

echo -n "54 18 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:supply:site-rank:reassess"
echo ""

echo -n "0 0 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:license:fetch"
echo ""

echo -n "0 0 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:serve-domains:update"
echo ""

echo -n "45 2 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:reports:clear"
echo ""

echo -n "35 3 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:targeting-reach:compute"
echo ""

echo -n "45 */3 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:targeting-reach:fetch"
echo ""

echo -n "35 4 * * * "
echo -n "find ${SERVICE_DIR}/storage/app/public -maxdepth 1 -type f -name '*.csv' -mtime +10 -delete"
echo -n " && "
echo -n "php ${SERVICE_DIR}/artisan ops:statistics:backup"
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

echo -n "5 */1 * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:events:clear --period=P7D"
echo ""

echo -n "12 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:targeting-options:update"
echo ""

echo -n "12 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:filtering-options:update"
echo ""

echo -n "*/5 * * * * "
echo -n "php ${SERVICE_DIR}/artisan ops:demand:cdn:upload"
echo ""

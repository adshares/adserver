#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=adserver

{
    echo "0    */12 * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ads:broadcast-host            &> /dev/null"

    echo "30   */6  * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ads:fetch-hosts -q            &> /dev/null"

    echo "*    *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:demand:inventory:import   &> /dev/null"
    echo "*    *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:adselect:inventory:export &> /dev/null"

    echo "*    *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:adpay:campaign:export     &> /dev/null"
    echo "*    *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:adpay:event:export        &> /dev/null"

    echo "15   *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:adpay:payments:get        &> /dev/null"
    echo "15   *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:demand:payments:prepare   &> /dev/null"
    echo "15   *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:demand:payments:send      &> /dev/null"

    echo "59   *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:demand:payments:block     &> /dev/null"

    echo "*/8  *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ads:get-tx-in                 &> /dev/null"
    echo "*/8  *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ads:process-tx                &> /dev/null"
    echo "*/8  *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:supply:payments:send      &> /dev/null"
    echo "*/8  *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:adselect:payment:export   &> /dev/null"

    echo "*/30 *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:wallet:transfer:cold      &> /dev/null"
    echo "*/30 *    * * * php ${INSTALLATION_DIR}/${SERVICE_NAME}/artisan ops:wallet:transfer:check     &> /dev/null"
} | tee crontab-`id --user --name`.txt

crontab crontab-`id --user --name`.txt

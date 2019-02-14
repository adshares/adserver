#!/usr/bin/env bash

set -ex

VENDOR_NAME=adshares
PROJECT_NAME=adserver

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/${VENDOR_NAME}}

{
echo "0    */12 * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ads-broadcast-host.sh &> /dev/null"
echo "30   */6  * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ads-fetch-hosts.sh    &> /dev/null"
echo "*    *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ops-inventory.sh      &> /dev/null"
echo "*    *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/adpay-export.sh       &> /dev/null"
echo "59   *    * * * $INSTALLATION_DIR/${PROJECT_NAME}/artisan ops:demand:payments:block            &> /dev/null"
echo "15   *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ops-payments.sh       &> /dev/null"
echo "*/8  *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ads-scanner.sh        &> /dev/null"
echo "*/30 *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ops-wallet.sh         &> /dev/null"
} | tee crontab.txt

crontab crontab.txt

#screen -S ${PROJECT_NAME}_worker -X quit || true
#screen -S ${PROJECT_NAME}_worker -dm bash -c "./artisan queue:work --queue=ads,default"

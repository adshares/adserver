#!/usr/bin/env bash

if [[ $EUID -eq 0 ]]
then
    echo "Don't be root when running $0" >&2
    exit 1
fi

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=adserver source ${HERE}/clone-service.sh
read_env ${INSTALLATION_DIR}/adserver/.env || read_env ${INSTALLATION_DIR}/adserver/.env.dist


INSTALL_SCHEME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADPANEL_URL" scheme 2>/dev/null`

INSTALL_HOSTNAME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADPANEL_URL" host 2>/dev/null`
INSTALL_HOSTNAME=${INSTALL_HOSTNAME:-localhost}

INSTALL_API_HOSTNAME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADSERVER_HOST" host 2>/dev/null`
INSTALL_API_HOSTNAME=${INSTALL_API_HOSTNAME:-127.0.0.1}

read_option INSTALL_HOSTNAME       "Adpanel domain (UI for advertisers and publishers)" 1
read_option INSTALL_API_HOSTNAME   "Adserver domain (serving banners)" 1

USE_HTTPS=Y
read_option USE_HTTPS "Configure for HTTPS?" 0 1

if [ "${USE_HTTPS^^}" == "Y" ]
then
    INSTALL_SCHEME=https
    BANNER_FORCE_HTTPS=true
else
    INSTALL_SCHEME=http
    BANNER_FORCE_HTTPS=false
fi

export INSTALL_HOSTNAME
export INSTALL_API_HOSTNAME

ADPANEL_URL="${INSTALL_SCHEME}://$INSTALL_HOSTNAME"
ADSERVER_HOST="${INSTALL_SCHEME}://${INSTALL_API_HOSTNAME}"
ADSERVER_BANNER_HOST=$ADSERVER_HOST

read_option ADSHARES_ADDRESS "ADS wallet address" 1
read_option ADSHARES_SECRET "ADS wallet secret" 1
read_option ADSHARES_NODE_HOST "ADS node hostname" 1
read_option ADSHARES_NODE_PORT "ADS node port" 1
read_option ADSHARES_OPERATOR_EMAIL "ADS wallet owner email (for balance alerts)" 1

ADSHARES_COMMAND=`which ads`
ADSHARES_WORKINGDIR="${INSTALLATION_DIR}/adserver/storage/wallet"
ADSERVER_ID=x`echo "${INSTALL_HOSTNAME}" | sha256sum | head -c 16`

read_option MAIL_HOST "mail smtp host" 1
read_option MAIL_PORT "mail smtp port" 1
read_option MAIL_USERNAME "mail smtp username" 1
read_option MAIL_PASSWORD "mail smtp password" 1
read_option MAIL_FROM_ADDRESS "mail from address" 1
read_option MAIL_FROM_NAME "mail from name" 1

INSTALL_ADUSER=Y
read_option INSTALL_ADUSER "Install local aduser service?" 0 1

if [ "${INSTALL_ADUSER^^}" == "Y" ]
then
    INSTALL_ADUSER_BROWSCAP=Y
    read_option INSTALL_ADUSER_BROWSCAP "Install local aduser browscap?" 0 1
    INSTALL_ADUSER_GEOLITE=Y
    read_option INSTALL_ADUSER_GEOLITE "Install local aduser geolite?" 0 1
else
    ADUSER_ENDPOINT="https://example.com/"
    read_option ADUSER_ENDPOINT "External aduser service endpoint" 1
    ADUSER_INTERNAL_LOCATION="$ADUSER_ENDPOINT"
    ADUSER_EXTERNAL_LOCATION="$ADUSER_ENDPOINT"
fi

INSTALL_ADSELECT=Y
read_option INSTALL_ADSELECT "Install local adselect service?" 0 1

if [ "${INSTALL_ADSELECT^^}" != "Y" ]
then
    ADSELECT_ENDPOINT="https://example.com"
    read_option ADSELECT_ENDPOINT "External adselect service endpoint" 1
fi

INSTALL_ADPAY=Y
read_option INSTALL_ADPAY "Install local adpay service?" 0 1

if [ "${INSTALL_ADPAY^^}" != "Y" ]
then
    ADPAY_ENDPOINT="https://example.com"
    read_option ADPAY_ENDPOINT "External adselect service endpoint" 1
fi

INSTALL_ADPANEL=Y
read_option INSTALL_ADPANEL "Install local adpanel service?" 0 1

if [ "${INSTALL_ADPANEL^^}" != "Y" ]
then
    ADPANEL_ENDPOINT="https://example.com"
    read_option ADPANEL_ENDPOINT "External adselect service endpoint" 1
fi

INSTALL_ADSERVER_CRON=Y
read_option INSTALL_ADSERVER_CRON "Install adserver cronjob?" 0 1

if [ "${INSTALL_ADUSER^^}" == "Y" ]
then
    ADUSER_EXTERNAL_LOCATION="${INSTALL_SCHEME}://$INSTALL_HOSTNAME/_aduser/"
    ADUSER_INTERNAL_LOCATION="${INSTALL_SCHEME}://$INSTALL_HOSTNAME/_aduser/"

    SERVICE_NAME=aduser source ${HERE}/clone-service.sh
    read_env ${INSTALLATION_DIR}/aduser/.env || read_env ${INSTALLATION_DIR}/aduser/.env.dist

    ADUSER_PORT=8003
    ADUSER_INTERFACE=127.0.0.1
    ADUSER_PIXEL_PATH=register

    save_env ${INSTALLATION_DIR}/aduser/.env.dist ${INSTALLATION_DIR}/aduser/.env


    cd ${INSTALLATION_DIR}/aduser
    ${HERE}/10-aduser.sh

    if [ "${INSTALL_ADUSER_BROWSCAP^^}" == "Y" ] ; then ${HERE}/11-aduser_browscap.sh; fi
    if [ "${INSTALL_ADUSER_GEOLITE^^}" == "Y" ] ; then ${HERE}/12-aduser_geolite.sh; fi
fi

if [ "${INSTALL_ADSELECT^^}" == "Y" ]
then
    ADSELECT_ENDPOINT=http://localhost:8011

    SERVICE_NAME=adselect source ${HERE}/clone-service.sh
    read_env ${INSTALLATION_DIR}/adselect/.env || read_env ${INSTALLATION_DIR}/adselect/.env.dist
    ADSELECT_SERVER_PORT=8011
    ADSELECT_SERVER_INTERFACE=127.0.0.1
    save_env ${INSTALLATION_DIR}/adselect/.env.dist ${INSTALLATION_DIR}/adselect/.env

    cd ${INSTALLATION_DIR}/adselect
    ${HERE}/20-adselect.sh
fi

if [ "${INSTALL_ADPAY^^}" == "Y" ]
then
    ADPAY_ENDPOINT=http://localhost:8012

    SERVICE_NAME=adpay source ${HERE}/clone-service.sh
    read_env ${INSTALLATION_DIR}/adpay/.env || read_env ${INSTALLATION_DIR}/adpay/.env.dist
    ADPAY_SERVER_PORT=8012
    ADPAY_SERVER_INTERFACE=127.0.0.1
    save_env ${INSTALLATION_DIR}/adpay/.env.dist ${INSTALLATION_DIR}/adpay/.env

    cd ${INSTALLATION_DIR}/adpay
    ${HERE}/30-adpay.sh
fi


if [ "${INSTALL_ADPANEL^^}" == "Y" ]
then
    ADSERVER_URL="$ADSERVER_HOST"

    unset APP_ENV
    SERVICE_NAME=adpanel source ${HERE}/clone-service.sh
    read_env ${INSTALLATION_DIR}/adpanel/.env || read_env ${INSTALLATION_DIR}/adpanel/.env.dist
    # adserver url
    save_env ${INSTALLATION_DIR}/adpanel/.env.dist ${INSTALLATION_DIR}/adpanel/.env

    cd ${INSTALLATION_DIR}/adpanel
    ${HERE}/50-adpanel.sh
fi

APP_URL=$ADSERVER_HOST
test -z "${APP_KEY}" && APP_KEY=base64:`date | sha256sum | head -c 32 | base64`
test -z "${ADSERVER_SECRET}" && ADSERVER_SECRET="${APP_KEY}"
save_env ${INSTALLATION_DIR}/adserver/.env.dist ${INSTALLATION_DIR}/adserver/.env
cd ${INSTALLATION_DIR}/adserver
DB_MIGRATE=1 ${HERE}/40-adserver.sh

if [ "${INSTALL_ADSERVER_CRON^^}" == "Y" ] ; then ${HERE}/41-adserver_worker.sh; fi

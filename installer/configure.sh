#!/usr/bin/env bash
set -e

source $(dirname $(readlink -f "$0"))/_functions.sh

read_env ${VENDOR_DIR}/adserver/.env || read_env ${VENDOR_DIR}/adserver/.env.dist

INSTALL_SCHEME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADPANEL_URL" scheme 2>/dev/null`

INSTALL_HOSTNAME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADPANEL_URL" host 2>/dev/null`
INSTALL_HOSTNAME=${INSTALL_HOSTNAME:-localhost}

INSTALL_API_HOSTNAME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$APP_URL" host 2>/dev/null`
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
ADSERVER_BANNER_HOST=${APP_URL}

read_option ADSHARES_ADDRESS "ADS wallet address" 1
read_option ADSHARES_SECRET "ADS wallet secret" 1
read_option ADSHARES_NODE_HOST "ADS node hostname" 1
read_option ADSHARES_NODE_PORT "ADS node port" 1
read_option ADSHARES_OPERATOR_EMAIL "ADS wallet owner email (for balance alerts)" 1

ADSHARES_COMMAND=`which ads`
ADSHARES_WORKINGDIR="${VENDOR_DIR}/adserver/storage/wallet"
ADSERVER_ID=x`echo "${INSTALL_HOSTNAME}" | sha256sum | head -c 16`

read_option MAIL_HOST "mail smtp host" 1
read_option MAIL_PORT "mail smtp port" 1
read_option MAIL_USERNAME "mail smtp username" 1
read_option MAIL_PASSWORD "mail smtp password" 1
read_option MAIL_FROM_ADDRESS "mail from address" 1
read_option MAIL_FROM_NAME "mail from name" 1

INSTALL_ADUSER=Y
read_option INSTALL_ADUSER "Install local aduser service?" 0 1

if [[ "${INSTALL_ADUSER^^}" == "Y" ]]
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

if [[ "${INSTALL_ADSELECT^^}" != "Y" ]]
then
    ADSELECT_ENDPOINT="https://example.com"
    read_option ADSELECT_ENDPOINT "External adselect service endpoint" 1
fi

INSTALL_ADPAY=Y
read_option INSTALL_ADPAY "Install local adpay service?" 0 1

if [[ "${INSTALL_ADPAY^^}" != "Y" ]]
then
    ADPAY_ENDPOINT="https://example.com"
    read_option ADPAY_ENDPOINT "External adselect service endpoint" 1
fi

INSTALL_ADPANEL=Y
read_option INSTALL_ADPANEL "Install local adpanel service?" 0 1

if [[ "${INSTALL_ADPANEL^^}" != "Y" ]]
then
    ADPANEL_ENDPOINT="https://example.com"
    read_option ADPANEL_ENDPOINT "External adselect service endpoint" 1
fi

INSTALL_ADSERVER_CRON=Y
read_option INSTALL_ADSERVER_CRON "Install adserver cronjob?" 0 1

if [[ "${INSTALL_ADUSER^^}" == "Y" ]]
then
    ADUSER_EXTERNAL_LOCATION="${INSTALL_SCHEME}://$INSTALL_HOSTNAME/_aduser/"
    ADUSER_INTERNAL_LOCATION="${INSTALL_SCHEME}://$INSTALL_HOSTNAME/_aduser/"

    read_env ${VENDOR_DIR}/aduser/.env || read_env ${VENDOR_DIR}/aduser/.env.dist

    ADUSER_PORT=8004
    ADUSER_INTERFACE=127.0.0.1
    ADUSER_PIXEL_PATH=register

    save_env ${VENDOR_DIR}/aduser/.env.dist ${VENDOR_DIR}/aduser/.env || echo "Skipped aduser/.env"
fi

if [[ "${INSTALL_ADSELECT^^}" == "Y" ]]
then
    ADSELECT_ENDPOINT=http://localhost:8011

    read_env ${VENDOR_DIR}/adselect/.env || read_env ${VENDOR_DIR}/adselect/.env.dist

    ADSELECT_SERVER_PORT=8011
    ADSELECT_SERVER_INTERFACE=127.0.0.1

    save_env ${VENDOR_DIR}/adselect/.env.dist ${VENDOR_DIR}/adselect/.env
fi

if [[ "${INSTALL_ADPAY^^}" == "Y" ]]
then
    ADPAY_ENDPOINT=http://localhost:8012

    read_env ${VENDOR_DIR}/adpay/.env || read_env ${VENDOR_DIR}/adpay/.env.dist

    ADPAY_SERVER_PORT=8012
    ADPAY_SERVER_INTERFACE=127.0.0.1

    save_env ${VENDOR_DIR}/adpay/.env.dist ${VENDOR_DIR}/adpay/.env
fi


if [[ "${INSTALL_ADPANEL^^}" == "Y" ]]
then
    ADSERVER_URL="$APP_URL"

    unset APP_ENV

    read_env ${VENDOR_DIR}/adpanel/.env || read_env ${VENDOR_DIR}/adpanel/.env.dist

    save_env ${VENDOR_DIR}/adpanel/.env.dist ${VENDOR_DIR}/adpanel/.env
fi

export APP_HOST=${INSTALL_API_HOSTNAME}
export APP_PORT=80
save_env ${VENDOR_DIR}/adserver/.env.dist ${VENDOR_DIR}/adserver/.env

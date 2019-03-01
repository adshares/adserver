#!/usr/bin/env bash

if [[ $EUID -ne 0 ]]
then
    echo "You need to be root to run this" >&2
    exit 1
fi

HERE=$(dirname $(readlink -f "$0"))
DO_INSTALL=1 source ${HERE}/_functions.sh

read_env ${INSTALLATION_DIR}/adserver/.env

INSTALL_HOSTNAME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADPANEL_URL" host 2>/dev/null`
INSTALL_HOSTNAME=${INSTALL_HOSTNAME:-localhost}

INSTALL_API_HOSTNAME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADSERVER_HOST" host 2>/dev/null`
INSTALL_API_HOSTNAME=${INSTALL_API_HOSTNAME:-127.0.0.1}

INSTALL_SCHEME=`php -r 'if(count($argv) == 3) echo parse_url($argv[1])[$argv[2]];' "$ADPANEL_URL" scheme 2>/dev/null`

export INSTALL_HOSTNAME
export INSTALL_API_HOSTNAME

if [ -e ${INSTALLATION_DIR}/aduser/.env ]
then
    cp -rf ${INSTALLATION_DIR}/.deployment-scripts/supervisor/conf.d/aduser*.conf /etc/supervisor/conf.d
fi

if [ -e ${INSTALLATION_DIR}/adselect/.env ]
then
    cp -rf ${INSTALLATION_DIR}/.deployment-scripts/supervisor/conf.d/adselect*.conf /etc/supervisor/conf.d
fi

if [ -e ${INSTALLATION_DIR}/adpay/.env ]
then
    cp -rf ${INSTALLATION_DIR}/.deployment-scripts/supervisor/conf.d/adpay*.conf /etc/supervisor/conf.d
fi

sudo service supervisor restart

envsubst '${INSTALL_HOSTNAME},${INSTALL_API_HOSTNAME}' < ${INSTALLATION_DIR}/.deployment-scripts/nginx/conf.d/adpanel.conf | sudo tee /etc/nginx/conf.d/adpanel.conf >/dev/null
envsubst '${INSTALL_HOSTNAME},${INSTALL_API_HOSTNAME}' < ${INSTALLATION_DIR}/.deployment-scripts/nginx/conf.d/adserver.conf | sudo tee /etc/nginx/conf.d/adserver.conf >/dev/null
sudo service nginx reload

cp -rf ${INSTALLATION_DIR}/.deployment-scripts/supervisor/conf.d/adserver*.conf /etc/supervisor/conf.d
sudo service supervisor restart

cp -rf ${INSTALLATION_DIR}/.deployment-scripts/php-fpm/pool.d/*.conf /etc/php/7.2/fpm/pool.d/
sudo service php7.2-fpm restart


if [ "${INSTALL_SCHEME^^}" == "HTTPS" ]
then
    INSTALL_CERTBOT=Y
    read_option INSTALL_CERTBOT "Do you want to setup SSL using Let's Encrypt / certbot" 0 1
    if [ "${INSTALL_CERTBOT^^}" == "HTTPS" ]
    then
        add-apt-repository ppa:certbot/certbot
        apt-get update
        apt-get install certbot python-certbot-nginx

        certbot --nginx
    fi
fi

echo "Initializing adserver"

function artisanCommand {
    sudo --login -u adshares ${INSTALLATION_DIR}/adserver/artisan --no-interaction $@
}

sleep 10

artisanCommand ops:targeting-options:update
artisanCommand ops:filtering-options:update
artisanCommand ads:fetch-hosts

echo "Install OK. Visit ${ADPANEL_URL}"
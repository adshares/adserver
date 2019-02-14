#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=adserver

source ${HERE}/clone-service.sh

cd ${INSTALLATION_DIR}/${SERVICE_NAME}

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners

export APP_NAME=AdServer
export APP_ENV=production
export APP_PORT=8001
export APP_DEBUG=false
export APP_URL=http://localhost:${APP_PORT} # publicly visible AdServer URL
export APP_KEY=base64:`date | sha256sum | head -c 32 | base64`

export LOG_CHANNEL=single

export DB_HOST=localhost
export DB_PORT=3306
export DB_DATABASE=${SERVICE_NAME}
export DB_USERNAME=${VENDOR_NAME}
export DB_PASSWORD=${VENDOR_NAME}

export BROADCAST_DRIVER=log
export CACHE_DRIVER=file
export SESSION_DRIVER=file

export SESSION_LIFETIME=120

export QUEUE_DRIVER=database

export MAIL_DRIVER=smtp # for testing purposes 'log` can be used
export MAIL_HOST=mailer
export MAIL_PORT=1025
export MAIL_USERNAME=1025
export MAIL_PASSWORD=
export MAIL_ENCRYPTION=null
export MAIL_FROM_ADDRESS=dev@adshares.net
export MAIL_FROM_NAME="[dev] AdShares"

export ADSERVER_SECRET=5LM0pJKnAlXDwSwSSqyJt
export ADSERVER_ID=AdShrek
export ADSERVER_HOST=http://localhost:${APP_PORT}
export ADSERVER_BANNER_HOST=http://localhost:${APP_PORT}

export ADSHARES_ADDRESS=0000-00000000-XXXX # account number (hot wallet) to be used by the server
export ADSHARES_NODE_HOST=t01.e11.click # account's node hostname
export ADSHARES_NODE_PORT=6511
export ADSHARES_SECRET= # account's secret key
export ADSHARES_COMMAND=`which ads`
export ADSHARES_WORKINGDIR=/tmp/adshares/ads-cache

export ADUSER_EXTERNAL_LOCATION=http://localhost:8010 # publicly visible AdServer URL
export ADUSER_INTERNAL_LOCATION=http://localhost:8010 # locally visible AdServer URL

export ADSELECT_ENDPOINT=http://localhost:8011 # locally visible AdSelect URL

export ADPAY_ENDPOINT=http://localhost:8012 # locally visible AdPay URL

export ADPANEL_URL=http://localhost # publicly visible AdPanel URL

composer install

yarn install
yarn run prod

function artisanCommand {
    ./artisan --no-interaction $@
}

artisanCommand config:cache
artisanCommand storage:link

if [[ ${DB_MIGRATE_FRESH:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh
elif [[ ${DB_MIGRATE_FRESH_FORCE:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh --force
elif [[ ${DB_MIGRATE_FRESH_FORCE_SEED:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh --force --seed
elif [[ ${DB_MIGRATE:-0} -eq 1 ]]
then
    artisanCommand migrate
fi

if [[ ${DB_SEED:-0} -eq 1 ]]
then
    artisanCommand db:seed
fi

artisanCommand ops:targeting-options:update
artisanCommand ops:filtering-options:update
artisanCommand ads:fetch-hosts

//TODO: remove when nginx configured
screen -S ${SERVICE_NAME} -X quit || true
screen -S ${SERVICE_NAME} -dm bash -c "php -S localhost:${APP_PORT} public/index.php"

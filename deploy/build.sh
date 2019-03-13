#!/usr/bin/env bash
set -e

HERE=${1:-$(dirname $(dirname $(readlink -f "$0")))}
source ${HERE}/_functions.sh

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners

GIT_TAG=$(git tag -l --points-at HEAD | head -n 1)
GIT_HASH="#"$(git rev-parse --short HEAD)

#export APP_VERSION=${APP_VERSION:-${GIT_TAG:-${GIT_HASH}}}
#export APP_NAME=AdServer
#export APP_ENV=production
#export APP_PORT=8001
#export APP_DEBUG=false
#export APP_URL=http://127.0.0.1:${APP_PORT} # publicly visible AdServer URL
#export APP_KEY=base64:`date | sha256sum | head -c 32 | base64`
#
#export LOG_CHANNEL=single
#
#export DB_HOST=127.0.0.1
#export DB_PORT=3306
#export DB_DATABASE=adserver
#export DB_USERNAME=${VENDOR_NAME}
#export DB_PASSWORD=${VENDOR_NAME}
#
#export BROADCAST_DRIVER=log
#export CACHE_DRIVER=file
#export SESSION_DRIVER=file
#
#export SESSION_LIFETIME=120
#
#export QUEUE_DRIVER=database
#
#export MAIL_DRIVER=smtp # for testing purposes 'log` can be used
#export MAIL_HOST=mailer
#export MAIL_PORT=1025
#export MAIL_USERNAME=1025
#export MAIL_PASSWORD=
#export MAIL_ENCRYPTION=null
#export MAIL_FROM_ADDRESS=dev@adshares.net
#export MAIL_FROM_NAME="[dev] AdShares"
#
#export ADSERVER_SECRET=5LM0pJKnAlXDwSwSSqyJt
#export ADSERVER_ID=AdShrek
#export ADSERVER_HOST=http://127.0.0.1:${APP_PORT}
#export ADSERVER_BANNER_HOST=http://127.0.0.1:${APP_PORT}
#
#export ADSHARES_ADDRESS=0000-00000000-XXXX # account number (hot wallet) to be used by the server
#export ADSHARES_NODE_HOST=t01.e11.click # account's node hostname
#export ADSHARES_NODE_PORT=6511
#export ADSHARES_SECRET= # account's secret key
#export ADSHARES_COMMAND=`which ads`
#export ADSHARES_WORKINGDIR=/tmp/adshares/ads-cache
#
#export ADSHARES_WALLET_COLD_ADDRESS=0000-00000000-XXXX
#E11="00000000000"
#export ADSHARES_WALLET_MAX_AMOUNT="10000$E11"
#export ADSHARES_WALLET_MIN_AMOUNT="5000$E11"
#export ADSHARES_OPERATOR_EMAIL=dev@adshares.net
#
#export ADUSER_EXTERNAL_LOCATION=http://127.0.0.1:8004 # publicly visible AdUser URL
#export ADUSER_INTERNAL_LOCATION=http://127.0.0.1:8004 # locally visible AdUser URL
#
#export ADSELECT_ENDPOINT=http://127.0.0.1:8011 # locally visible AdSelect URL
#
#export ADPAY_ENDPOINT=http://127.0.0.1:8012 # locally visible AdPay URL
#
#export ADPANEL_URL=http://127.0.0.1 # publicly visible AdPanel URL

composer install --no-dev

yarn install
yarn run prod

function artisanCommand {
    ./artisan --verbose --no-interaction "$@"
}

artisanCommand key:generate
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

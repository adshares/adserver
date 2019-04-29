#!/usr/bin/env bash
# Usage: build.sh [<location-of-functions-file-to-include> [<work-dir>]]
[[ -z ${1:-""} ]] && set -eu || source ${1}/_functions.sh --vendor
cd ${2:-"."}

export APP_VERSION=$(versionFromGit)

function artisanCommand {
    ./artisan --no-interaction "$@"
}

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners
mkdir -pm 777 storage/framework/views

ln -sf ${SERVICE_DIR}/storage/app/public public/storage

composer install --no-dev

yarn install
yarn run prod

rm -f bootstrap/cache/config.php
artisanCommand config:cache
artisanCommand key:generate

if [[ ${DB_MIGRATE_FRESH:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh
elif [[ ${DB_MIGRATE_FRESH_FORCE:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh --force
elif [[ ${DB_MIGRATE_FRESH_FORCE_SEED:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh --force --seed
elif [[ ${SKIP_DB_MIGRATE:-0} -eq 0 ]]
then
    artisanCommand migrate
fi

if [[ ${_DB_SEED:-0} -eq 1 ]]
then
    artisanCommand db:seed
fi

if [[ ${_UPDATE_TARGETING:-0} -eq 1 ]]
then
    artisanCommand ops:targeting-options:update
fi

if [[ ${_UPDATE_FILTERING:-0} -eq 1 ]]
then
    artisanCommand ops:filtering-options:update
fi

if [[ ${_UPDATE_NETWORK_HOSTS:-0} -eq 1 ]]
then
    artisanCommand ads:fetch-hosts --quiet
fi

if [[ ${_BROADCAST_SERVER:-0} -eq 1 ]]
then
    artisanCommand ads:broadcast-host
fi

if [[ ${_CREATE_ADMIN:-0} -eq 1 ]]
then
    artisanCommand ops:admin:create --password
fi

artisanCommand ops:exchange-rate:fetch

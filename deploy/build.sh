#!/usr/bin/env bash
source ${1}/_functions.sh --vendor
[[ -z ${2:-""} ]] || cd $2

export APP_VERSION=$(versionFromGit)

function artisanCommand {
    ./artisan --no-interaction "$@"
}

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners

composer install --no-dev

yarn install
yarn run prod

artisanCommand key:generate
artisanCommand storage:link
artisanCommand config:cache

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

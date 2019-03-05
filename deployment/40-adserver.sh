#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=adserver

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners

envsubst < info.json.template | tee public/info.json

composer install --no-dev

yarn install
yarn run prod

function artisanCommand {
    ./artisan --no-interaction $@
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
#!/usr/bin/env bash
set -e

source ${1:-$(dirname $(readlink -f "$0"))/bin}/_functions.sh

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners

GIT_TAG=$(git tag -l --points-at HEAD | head -n 1)
GIT_HASH="#"$(git rev-parse --short HEAD)

set -a
source .env
set +a

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

if [[ ${SKIP_TARGETING:-0} -ne 1 ]]
then
echo "$0"
env | sort | grep SKIP_ || echo "NO SKIP_..."
    artisanCommand ops:targeting-options:update
fi

if [[ ${SKIP_FILTERING:-0} -ne 1 ]]
then
    artisanCommand ops:filtering-options:update
fi

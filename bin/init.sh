#!/usr/bin/env bash

set -e

export SYSTEM_USER_ID=`id --user`
export SYSTEM_USER_NAME=`id --user --name`

export WEBSERVER_PORT=${WEBSERVER_PORT:-8101}
export MAILER_PORT=${MAILER_PORT:-8025}

export ADSERVER_URL=${ADSERVER_URL:-http://localhost:8101}
export ADPANEL_URL=${ADPANEL_URL:-http://localhost:8102}

[ -f .env ] || envsubst < .env.dist | tee .env

source .env

docker-compose run --rm dev composer install
docker-compose run --rm dev composer dump-autoload
docker-compose run --rm dev npm install
docker-compose run --rm dev npm run dev

chmod a+w -R storage

docker-compose up --detach

docker-compose exec dev ./artisan migrate
docker-compose exec dev ./artisan package:discover
docker-compose exec dev ./artisan browsercap:updater

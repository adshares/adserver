#!/usr/bin/env bash

set -e

export SYSTEM_USER_ID=`id --user`
export SYSTEM_USER_NAME=`id --user --name`

export WEBSERVER_PORT=${WEBSERVER_PORT:-8101}
export MAILER_PORT=${MAILER_PORT:-8025}
export MAILER_HOST=${MAILER_HOST:-mailer}

export ADSERVER_URL=${ADSERVER_URL:-http://localhost:8101}
export ADPANEL_URL=${ADPANEL_URL:-http://localhost:8102}

[ -f .env ] || envsubst < .env.dist | tee .env

source .env

docker-compose run --rm worker composer install
docker-compose run --rm worker composer dump-autoload
docker-compose run --rm worker ./artisan package:discover
docker-compose run --rm worker ./artisan browsercap:updater
docker-compose run --rm worker npm install
docker-compose run --rm worker npm run dev

chmod a+w -R storage


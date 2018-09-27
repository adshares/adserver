#!/usr/bin/env bash

set -e -x

cp --no-clobber --verbose docker-compose.override.yaml.dist docker-compose.override.yaml
[ -f .env ] || SYSTEM_USER_ID=`id --user` SYSTEM_USER_NAME=`id --user --name` envsubst \${SYSTEM_USER_ID},\${SYSTEM_USER_NAME} < .env.dist | tee .env

docker-compose config # just to check the config
docker-compose run --rm dev composer install
docker-compose run --rm dev composer dump-autoload

chmod a+w -R storage

docker-compose up --detach
docker-compose exec dev ./artisan migrate
docker-compose exec dev ./artisan package:discover
docker-compose exec dev ./artisan browsercap:updater
docker-compose exec dev npm install
docker-compose exec dev npm run dev

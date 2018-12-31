#!/usr/bin/env bash

set -ex

# Create installation directory
mkdir -p ${INSTALLATION_PATH}

# Move directories
mv * ${INSTALLATION_PATH}/
mv .env* ${INSTALLATION_PATH}/

mkdir -pm 777 ${INSTALLATION_PATH}/storage

cd ${INSTALLATION_PATH}

if [ ! -v TRAVIS ]; then
  ./artisan config:cache
fi

./artisan migrate:fresh
./artisan db:seed

crontab -u ${INSTALLATION_USER} ./docker/cron/crontab

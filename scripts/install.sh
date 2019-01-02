#!/usr/bin/env bash

set -ex

# Create installation directory
mkdir -p ${INSTALLATION_PATH}

# Move directories
mv * ${INSTALLATION_PATH}/
mv .env* ${INSTALLATION_PATH}/

mkdir -pm 777 ${INSTALLATION_PATH}/storage
mkdir -pm 777 ${EXTERNAL_STORAGE_PATH:-/var/www/adserver-storage}

cd ${INSTALLATION_PATH}

if [ ! -v TRAVIS ]; then
  ./artisan config:cache
fi

crontab -u ${INSTALLATION_USER} -r

#supervisorctl stop adselect
#supervisorctl stop adpay

#./artisan migrate:fresh
#./artisan db:seed
#mongo --eval 'db.dropDatabase()' adselect
#mongo --eval 'db.dropDatabase()' adpay

#supervisorctl start adselect
#supervisorctl start adpay

#./artisan ops:targeting-options:update
./artisan ads:fetch-hosts

crontab -u ${INSTALLATION_USER} ./docker/cron/crontab

service php7.2-fpm restart

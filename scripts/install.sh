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

if [[ ${DO_RESET} -eq 1 ]]
then
    supervisorctl stop adselect${DEPLOYMENT_SUFFIX}
    supervisorctl stop adpay${DEPLOYMENT_SUFFIX}

    ./artisan migrate:fresh
    ./artisan db:seed

    mongo --eval 'db.dropDatabase()' adselect${DEPLOYMENT_SUFFIX}
    mongo --eval 'db.dropDatabase()' adpay${DEPLOYMENT_SUFFIX}

    supervisorctl start adselect${DEPLOYMENT_SUFFIX}
    supervisorctl start adpay${DEPLOYMENT_SUFFIX}
else
    ./artisan migrate
fi

./artisan ops:targeting-options:update
./artisan ads:fetch-hosts

crontab -u ${INSTALLATION_USER} ./docker/cron/crontab

service php7.2-fpm restart

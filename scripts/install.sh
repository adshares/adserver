#!/usr/bin/env bash

set -ex

crontab -u ${INSTALLATION_USER} -r || echo "No previous crontab for ${INSTALLATION_USER}"
supervisorctl stop ${SUPERVISOR_CONFIG_NAME}

# Create installation directory
mkdir -p ${INSTALLATION_PATH}

# Move directories
mv * ${INSTALLATION_PATH}/
mv .env* ${INSTALLATION_PATH}/
rm -rf ${INSTALLATION_PATH}/node_modules

mkdir -pm 777 ${INSTALLATION_PATH}/storage
mkdir -pm 777 ${EXTERNAL_STORAGE_PATH:-/var/www/adserver-storage}

cd ${INSTALLATION_PATH}

if [ ! -v TRAVIS ]; then
  ./artisan config:cache
fi


if [[ ${DO_RESET} == "yes" ]]
then
    supervisorctl stop adselect${DEPLOYMENT_SUFFIX}
    supervisorctl stop adpay${DEPLOYMENT_SUFFIX}
#    supervisorctl stop aduser${DEPLOYMENT_SUFFIX}

    ./artisan migrate:fresh
    ./artisan db:seed

    mongo --eval 'db.dropDatabase()' adselect${DEPLOYMENT_SUFFIX}
    mongo --eval 'db.dropDatabase()' adpay${DEPLOYMENT_SUFFIX}
#    mongo --eval 'db.dropDatabase()' aduser${DEPLOYMENT_SUFFIX}

    supervisorctl start adselect${DEPLOYMENT_SUFFIX}
    supervisorctl start adpay${DEPLOYMENT_SUFFIX}
#    supervisorctl start aduser${DEPLOYMENT_SUFFIX}
elif [[ ${DO_RESET} == "both" ]]
then
    supervisorctl stop adpay${DEPLOYMENT_SUFFIX}
    mongo --eval 'db.dropDatabase()' adpay${DEPLOYMENT_SUFFIX}
    supervisorctl start adpay${DEPLOYMENT_SUFFIX}

    supervisorctl stop adselect${DEPLOYMENT_SUFFIX}
    mongo --eval 'db.dropDatabase()' adselect${DEPLOYMENT_SUFFIX}
    supervisorctl start adselect${DEPLOYMENT_SUFFIX}
else
    ./artisan migrate
fi

./artisan ops:targeting-options:update
./artisan ops:filtering-options:update
./artisan ads:fetch-hosts --quiet

crontab -u ${INSTALLATION_USER} ./docker/cron/crontab

service php7.2-fpm restart

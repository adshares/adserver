#!/usr/bin/env bash

set -ex

ARTISAN_COMMAND="${INSTALLATION_PATH}/artisan --no-interaction"

service nginx stop
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
  ${ARTISAN_COMMAND} config:cache
fi

if [[ ${DO_RESET} == "yes" ]]
then
    supervisorctl stop adselect${DEPLOYMENT_SUFFIX}
    supervisorctl stop adpay${DEPLOYMENT_SUFFIX}
#    supervisorctl stop aduser${DEPLOYMENT_SUFFIX}

    if [[ "${BUILD_BRANCH:-master}" == "master" ]]
    then
        ${ARTISAN_COMMAND} migrate 
    else
        ${ARTISAN_COMMAND} migrate:fresh --force 
        ${ARTISAN_COMMAND} db:seed
    fi

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

    ${ARTISAN_COMMAND} migrate 
else
    ${ARTISAN_COMMAND} migrate 
fi

${ARTISAN_COMMAND} ops:targeting-options:update
${ARTISAN_COMMAND} ops:filtering-options:update
${ARTISAN_COMMAND} ads:fetch-hosts --quiet

crontab -u ${INSTALLATION_USER} ./docker/cron/crontab

service php7.2-fpm restart
service nginx start

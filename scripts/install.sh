#!/usr/bin/env bash

set -e

function sudoBash {
    sudo -E -u ${INSTALLATION_USER} -- bash -c "$*"
}

function artisanCommand {
    sudoBash ${INSTALLATION_PATH}/artisan --no-interaction $@
}

crontab -u ${INSTALLATION_USER} -r || echo "No previous crontab for ${INSTALLATION_USER}"

set -a
source .env.from-build
set +a

# Create installation directory
mkdir -p ${INSTALLATION_PATH}

# Move directories
mv * ${INSTALLATION_PATH}/
mv .env* ${INSTALLATION_PATH}/
rm -rf ${INSTALLATION_PATH}/node_modules

mkdir -pm 777 ${ADSHARES_WORKINGDIR}
mkdir -pm 777 ${INSTALLATION_PATH}/storage
mkdir -pm 777 ${INSTALLATION_PATH}/storage/framework/views

chown -R ${INSTALLATION_USER} ${INSTALLATION_PATH}
cd ${INSTALLATION_PATH}

if [ ! -v TRAVIS ]; then
    set -a
    source .env.from-build
    set +a
    artisanCommand config:cache
fi

if [[ ${DO_RESET} == "yes" ]]
then
    supervisorctl stop adselect${DEPLOYMENT_SUFFIX}
    supervisorctl stop adpay${DEPLOYMENT_SUFFIX}

    if [[ "${BUILD_BRANCH:-master}" == "master" ]]
    then
        artisanCommand migrate --force
    else
        artisanCommand migrate:fresh --force --seed
        artisanCommand db:seed --force
    fi

    mongo --eval 'db.dropDatabase()' adselect${DEPLOYMENT_SUFFIX}
    mongo --eval 'db.dropDatabase()' adpay${DEPLOYMENT_SUFFIX}

    supervisorctl start adselect${DEPLOYMENT_SUFFIX}
    supervisorctl start adpay${DEPLOYMENT_SUFFIX}

elif [[ ${DO_RESET} == "both" ]]
then
    supervisorctl stop adpay${DEPLOYMENT_SUFFIX}
    mongo --eval 'db.dropDatabase()' adpay${DEPLOYMENT_SUFFIX}
    supervisorctl start adpay${DEPLOYMENT_SUFFIX}

    supervisorctl stop adselect${DEPLOYMENT_SUFFIX}
    mongo --eval 'db.dropDatabase()' adselect${DEPLOYMENT_SUFFIX}
    supervisorctl start adselect${DEPLOYMENT_SUFFIX}

    artisanCommand migrate 
else
    artisanCommand migrate --force
fi

artisanCommand storage:link
artisanCommand ops:targeting-options:update
artisanCommand ops:filtering-options:update

crontab -u ${INSTALLATION_USER} ./docker/cron/crontab-${VARIABLE_HOST}


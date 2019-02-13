#!/usr/bin/env bash

set -ex

PROJECT_NAME=aduser

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/adshares}

GIT_BRANCH_NAME=${GIT_BRANCH_NAME:-master}
GIT_REPO_BASE_URL=${GIT_REPO_BASE_URL:-https://github.com/adshares}

cd ${INSTALLATION_DIR}

git clone --depth=1 --single-branch --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${PROJECT_NAME}.git \
    || ( cd ${INSTALLATION_DIR}/${PROJECT_NAME} && git fetch && git reset --hard && git checkout ${GIT_BRANCH_NAME} )

# ===

PROJECT_DATA_DIR="${INSTALLATION_DIR}/${PROJECT_NAME}_data"

if [[ ${INSTALL_GEOLITE_DATA:-0} -eq 1 ]]
then
    mkdir -p ${PROJECT_DATA_DIR}

    curl http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.tar.gz --silent --show-error \
      | tar --directory=${PROJECT_DATA_DIR} --gzip --extract --no-anchored --strip-components 1 'GeoLite2-City.mmdb'
fi

if [[ ${INSTALL_BROWSCAP_DATA:-0} -eq 1 ]]
then
    mkdir -p ${PROJECT_DATA_DIR}

    TEMP_FILE="$(mktemp).zip"
    curl https://browscap.org/stream?q=BrowsCapZIP --silent --show-error -o ${TEMP_FILE}
    test -f ${PROJECT_DATA_DIR}/browscap.csv || unzip ${TEMP_FILE} browscap.csv -d ${PROJECT_DATA_DIR}
    rm ${TEMP_FILE}
fi

export PYTHONUNBUFFERED=1

# ===

cd ${INSTALLATION_DIR}/${PROJECT_NAME}/${PROJECT_NAME}_data_services
pipenv install

export ADUSER_DATA_BROWSCAP_SOCK_FILE=/tmp/apshares/${PROJECT_NAME}-data-browscap.sock
export ADUSER_DATA_BROWSCAP_CSV_PATH=${PROJECT_DATA_DIR}/browscap.csv

screen -S ${PROJECT_NAME}_browscap -X quit || true
screen -S ${PROJECT_NAME}_browscap -dm bash -c "pipenv run python ${PROJECT_NAME}_data_services/browscap/daemon.py"

export ADUSER_DATA_GEOLITE_SOCK_FILE=/tmp/adshares/${PROJECT_NAME}-data-geolite.sock
export ADUSER_DATA_GEOLITE_PATH=${PROJECT_DATA_DIR}/GeoLite2-City.mmdb

screen -S ${PROJECT_NAME}_geolite -X quit || true
screen -S ${PROJECT_NAME}_geolite -dm bash -c "pipenv run python ${PROJECT_NAME}_data_services/geolite/daemon.py"

# ===

cd ${INSTALLATION_DIR}/${PROJECT_NAME}
pipenv install

export ADUSER_DATA_PROVIDER=${PROJECT_NAME}.data.examples.example
export ADUSER_MOCK_DATA_PATH=${PROJECT_DATA_DIR}/mock.json

#export ADUSER_DATA_BROWSCAP_SOCK_FILE=${ADUSER_DATA_BROWSCAP_SOCK_FILE:-"/tmp/adshares/${PROJECT_NAME}-data-browscap.sock"}
#export ADUSER_DATA_GEOLITE_SOCK_FILE=${ADUSER_DATA_GEOLITE_SOCK_FILE:-"/tmp/adshares/${PROJECT_NAME}-data-geolite.sock"}

export ADUSER_MONGO_DB_PORT=27017
export ADUSER_MONGO_DB_NAME=${PROJECT_NAME}
export ADUSER_MONGO_DB_HOST=localhost

export ADUSER_PORT=8010
export ADUSER_PIXEL_PATH=register
export ADUSER_DEBUG_WITHOUT_CACHE=0

export ADUSER_TRACKING_SECRET=secret
export ADUSER_COOKIE_NAME=cookie_name
export ADUSER_COOKIE_EXPIRY_PERIOD=4w
export ADUSER_LOG_CONFIG_JSON_FILE=
export ADUSER_LOG_LEVEL=DEBUG

screen -S ${PROJECT_NAME} -X quit || true
screen -S ${PROJECT_NAME} -dm bash -c "pipenv run python daemon.py"

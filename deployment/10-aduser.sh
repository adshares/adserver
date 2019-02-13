#!/usr/bin/env bash

set -ex

VENDOR_NAME=adshares
PROJECT_NAME=aduser

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/${VENDOR_NAME}}

GIT_BRANCH_NAME=${GIT_BRANCH_NAME:-master}
GIT_REPO_BASE_URL=${GIT_REPO_BASE_URL:-https://github.com/${VENDOR_NAME}}

cd ${INSTALLATION_DIR}

git clone --depth=1 --single-branch --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${PROJECT_NAME}.git \
    || ( cd ${INSTALLATION_DIR}/${PROJECT_NAME} && git fetch && git reset --hard && git checkout ${GIT_BRANCH_NAME} )

# ===

PROJECT_DATA_DIR="${INSTALLATION_DIR}/${PROJECT_NAME}_data"

#screen -S ${PROJECT_NAME} -X quit || true
#cd ${INSTALLATION_DIR}/${PROJECT_NAME}
#pipenv install
#
#export PYTHONUNBUFFERED=1
#
#export ADUSER_DATA_PROVIDER=${PROJECT_NAME}.data.examples.example
#export ADUSER_MOCK_DATA_PATH=${PROJECT_DATA_DIR}/mock.json
#
#export ADUSER_DATA_BROWSCAP_SOCK_FILE=${ADUSER_DATA_BROWSCAP_SOCK_FILE:-"/tmp/${VENDOR_NAME}/${PROJECT_NAME}-data-browscap.sock"}
#export ADUSER_DATA_GEOLITE_SOCK_FILE=${ADUSER_DATA_GEOLITE_SOCK_FILE:-"/tmp/${VENDOR_NAME}/${PROJECT_NAME}-data-geolite.sock"}
#
#export ADUSER_MONGO_DB_PORT=27017
#export ADUSER_MONGO_DB_NAME=${PROJECT_NAME}
#export ADUSER_MONGO_DB_HOST=localhost
#
#export ADUSER_PORT=8010
#export ADUSER_PIXEL_PATH=register
#export ADUSER_DEBUG_WITHOUT_CACHE=0
#
#export ADUSER_TRACKING_SECRET=secret
#export ADUSER_COOKIE_NAME=cookie_name
#export ADUSER_COOKIE_EXPIRY_PERIOD=4w
#export ADUSER_LOG_CONFIG_JSON_FILE=
#export ADUSER_LOG_LEVEL=DEBUG
#
#screen -S ${PROJECT_NAME} -dm bash -c "pipenv run python daemon.py"

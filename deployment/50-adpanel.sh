#!/usr/bin/env bash

set -ex

VENDOR_NAME=adshares
PROJECT_NAME=adpanel

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/adshares}

GIT_BRANCH_NAME=${GIT_BRANCH_NAME:-master}
GIT_REPO_BASE_URL=${GIT_REPO_BASE_URL:-https://github.com/adshares}

cd ${INSTALLATION_DIR}

git clone --depth=1 --single-branch --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${PROJECT_NAME}.git \
    || ( cd ${INSTALLATION_DIR}/${PROJECT_NAME} && git fetch && git reset --hard && git checkout ${GIT_BRANCH_NAME} )

cd ${INSTALLATION_DIR}/${PROJECT_NAME}

ADSERVER_URL_FROM_CMD=${1:-http://localhost:8001}

GIT_TAG=$(git tag -l --points-at HEAD | head -n 1)
GIT_HASH="#"$(git rev-parse --short HEAD)

export APP_VERSION=${APP_VERSION:-${GIT_TAG:-${GIT_HASH}}}
export APP_PROD=${APP_PROD:-true}
export ADSERVER_URL=${ADSERVER_URL:-${ADSERVER_URL_FROM_CMD}}
export DEV_XDEBUG=${DEV_XDEBUG:-false}
export APP_ENV=${APP_ENV:-prod}
export APP_PORT=${APP_PORT:-8002}

envsubst < src/environments/environment.ts.template | tee src/environments/environment.${APP_ENV}.ts

yarn install

screen -S ${PROJECT_NAME} -X quit || true

if [[ ${APP_ENV} == 'dev' ]]
then
    screen -S ${PROJECT_NAME} -dm bash -c "yarn start --port $APP_PORT"
elif [[ ${APP_ENV} == 'prod' ]]
then
    screen -S ${PROJECT_NAME} -dm bash -c "yarn start --prod --port $APP_PORT"
else
    echo "ERROR: Unsupported environment ($APP_ENV)."
    exit 1
fi

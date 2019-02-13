#!/usr/bin/env bash

set -ex

PROJECT_NAME=adselect

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/adshares}

GIT_BRANCH_NAME=${GIT_BRANCH_NAME:-master}
GIT_REPO_BASE_URL=${GIT_REPO_BASE_URL:-https://github.com/adshares}

cd ${INSTALLATION_DIR}

git clone --depth=1 --single-branch --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${PROJECT_NAME}.git \
    || ( git fetch && git reset --hard && git checkout ${GIT_BRANCH_NAME} )

cd ${INSTALLATION_DIR}/${PROJECT_NAME}

pipenv install

screen -S ${PROJECT_NAME} -dm bash -c "pipenv run python daemon.py"

#!/usr/bin/env bash

echo "- ${SERVICE_NAME} -"
test -z ${SERVICE_NAME:-$1} && echo "Missing SERVICE_NAME" >&2 && exit 1
test -z ${INSTALLATION_DIR:-$2} && echo "Missing INSTALLATION_DIR" >&2 && exit 1

GIT_BRANCH_NAME=${GIT_BRANCH_NAME:-master}
GIT_REPO_BASE_URL=${GIT_REPO_BASE_URL:-https://github.com/adshares}

git clone --depth=1 --single-branch --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${SERVICE_NAME}.git ${INSTALLATION_DIR}/${SERVICE_NAME} \
    || ( cd ${INSTALLATION_DIR}/${SERVICE_NAME} && git fetch && git reset --hard && git checkout ${GIT_BRANCH_NAME} )

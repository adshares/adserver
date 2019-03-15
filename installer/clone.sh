#!/usr/bin/env bash
set -e

SERVICE_NAME=${SERVICE_NAME:-$1}
GIT_BRANCH_NAME=${GIT_BRANCH_NAME:-${2:-master}}
VENDOR_DIR=${VENDOR_DIR:-$3}
GIT_REPO_BASE_URL=${GIT_REPO_BASE_URL:-${4:-https://github.com/adshares}}

source $(dirname $(readlink -f "$0"))/_functions.sh any

test -z ${SERVICE_NAME} && echo "Missing SERVICE_NAME" >&2 && exit 1
test -z ${VENDOR_DIR} && echo "Missing VENDOR_DIR" >&2 && exit 1

echo "Clone or update source for ${SERVICE_NAME} (${GIT_BRANCH_NAME})"

#git clone --depth=1 --single-branch --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${SERVICE_NAME}.git ${VENDOR_DIR}/${SERVICE_NAME} &> /dev/null \
git clone --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${SERVICE_NAME}.git ${VENDOR_DIR}/${SERVICE_NAME} &> /dev/null \
|| (\
    cd ${VENDOR_DIR}/${SERVICE_NAME} \
    && git reset --hard \
    && git fetch --tags \
    && git checkout ${GIT_BRANCH_NAME} \
    && git pull \
)


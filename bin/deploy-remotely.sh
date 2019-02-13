#!/usr/bin/env bash

if [[ "$1" == "" ]]
then
    echo "Provide remote server name (for ssh & scp)"
    exit 1
else
    REMOTE_HOST="$1"
fi

set -ex

INSTALLATION_USER=${INSTALLATION_USER:-"adshares"}

HERE=$(dirname $(readlink -f "$0"))
THERE=$(dirname ${HERE})/deployment

# ===

TEMP_DIR=$(ssh ${REMOTE_HOST} "bash -c 'mktemp --directory'")
scp ${THERE}/*.sh ${REMOTE_HOST}:${TEMP_DIR}

# ===

ssh ${REMOTE_HOST} sudo --login "bash --login -c 'INSTALLATION_USER=${INSTALLATION_USER} ${TEMP_DIR}/bootstrap.sh'"

# ===

ssh ${REMOTE_HOST} sudo --login --user=${INSTALLATION_USER} "bash --login -c '${TEMP_DIR}/bootstrap.sh'"

# ===

ssh ${REMOTE_HOST} sudo --login "bash --login -c 'rm -rf ${TEMP_DIR}'"

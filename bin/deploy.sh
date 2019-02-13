#!/usr/bin/env bash

set -ex

INSTALLATION_USER=${INSTALLATION_USER:-"adshares"}
INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/adshares}

HERE=$(dirname $(readlink -f "$0"))
THERE=$(dirname ${HERE})/deployment

# ===

TEMP_DIR=$(ssh dev "bash -c 'mktemp --directory'")
scp ${THERE}/*.sh dev:${TEMP_DIR}

# ===

ssh dev sudo --login "bash --login -c 'id --user ${INSTALLATION_USER} || useradd --no-user-group --create-home --shell /bin/bash ${INSTALLATION_USER}'"
ssh dev sudo chown -R ${INSTALLATION_USER} ${TEMP_DIR}

# ===

ssh dev sudo --login "bash --login -c '${TEMP_DIR}/bootstrap.sh'"

# ===

ssh dev sudo --login "bash --login -c 'mkdir -p ${INSTALLATION_DIR}'"
ssh dev sudo --login "bash --login -c 'chown -R ${INSTALLATION_USER} ${INSTALLATION_DIR}'"

# ===

ssh dev sudo --login --user=${INSTALLATION_USER} "INSTALLATION_DIR=${INSTALLATION_DIR} bash --login -c '${TEMP_DIR}/bootstrap.sh'"

# ===

ssh dev sudo --login "bash --login -c 'rm -rf ${TEMP_DIR}'"



#!/usr/bin/env bash

INSTALLATION_USER=${INSTALLATION_USER:-$1}
test -z ${INSTALLATION_USER} && echo "Missing INSTALLATION_USER" >&2 && exit 1

INSTALLATION_DIR=${INSTALLATION_DIR:-$2}
test -z ${INSTALLATION_DIR} && echo "Missing INSTALLATION_DIR" >&2 && exit 1

HERE=${HERE:-$3}
test -z ${HERE:-$3} &>/dev/null && echo "Missing HERE (source dir for scripts)" >&2 && exit 1

VENDOR_NAME=${VENDOR_NAME:-$4}
test -z ${VENDOR_NAME:-$4} && echo "Missing VENDOR_NAME" >&2 && exit 1

id --user ${INSTALLATION_USER} &>/dev/null || useradd --no-user-group --create-home --shell /bin/bash ${INSTALLATION_USER}

rm -rf ${INSTALLATION_DIR}/.deployment-scripts && cp -rf ${HERE} ${INSTALLATION_DIR}/.deployment-scripts

LOG_DIR=${LOG_DIR:-/var/log/${VENDOR_NAME}}
mkdir -p ${LOG_DIR}

chown -R ${INSTALLATION_USER}:`id --group --name ${INSTALLATION_USER}` ${INSTALLATION_DIR} ${LOG_DIR}

#!/usr/bin/env bash

INSTALLATION_USER=${INSTALLATION_USER:-$1}
test -z ${INSTALLATION_USER} && echo "Missing INSTALLATION_USER" >&2 && exit 1

INSTALLATION_DIR=${INSTALLATION_DIR:-$2}
test -z ${INSTALLATION_DIR} && echo "Missing INSTALLATION_DIR" >&2 && exit 1
test -d ${INSTALLATION_DIR} || mkdir -p ${INSTALLATION_DIR}
mkdir -p ${INSTALLATION_DIR}/.backup

HERE=${HERE:-$3}
test -z ${HERE} &>/dev/null && echo "Missing HERE (source dir for scripts)" >&2 && exit 1

VENDOR_NAME=${VENDOR_NAME:-$4}
test -z ${VENDOR_NAME} && echo "Missing VENDOR_NAME" >&2 && exit 1

test -d ${INSTALLATION_DIR}/.deployment-scripts && rm -rf ${INSTALLATION_DIR}/.deployment-scripts
cp -r ${HERE} ${INSTALLATION_DIR}/.deployment-scripts

LOG_DIR=${LOG_DIR:-/var/log/${VENDOR_NAME}}
mkdir -p ${LOG_DIR}

id --user ${INSTALLATION_USER} &>/dev/null || useradd --create-home --shell /bin/bash ${INSTALLATION_USER}
chown -R ${INSTALLATION_USER}:`id --group --name ${INSTALLATION_USER}` ${INSTALLATION_DIR} ${LOG_DIR}

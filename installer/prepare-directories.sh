#!/usr/bin/env bash
set -e

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh root

#===

test -z ${BACKUP_DIR}  && echo "Missing BACKUP_DIR"  >&2 && exit 1
test -z ${DATA_DIR}    && echo "Missing DATA_DIR"    >&2 && exit 1
test -z ${LOG_DIR}     && echo "Missing LOG_DIR"     >&2 && exit 1
test -z ${RUN_DIR}     && echo "Missing RUN_DIR"     >&2 && exit 1
test -z ${SCRIPT_DIR}  && echo "Missing SCRIPT_DIR"  >&2 && exit 1

test -z ${VENDOR_DIR}  && echo "Missing VENDOR_DIR"  >&2 && exit 1

test -z ${VENDOR_USER} && echo "Missing VENDOR_USER" >&2 && exit 1

mkdir -p ${BACKUP_DIR}
mkdir -p ${DATA_DIR}
mkdir -p ${LOG_DIR}
mkdir -p ${RUN_DIR}
mkdir -p ${SCRIPT_DIR}

mkdir -p ${VENDOR_DIR}

id --user ${VENDOR_USER} &>/dev/null || useradd --create-home --shell /bin/bash ${VENDOR_USER}

chown -R ${VENDOR_USER}:`id --group --name ${VENDOR_USER}` ${BACKUP_DIR} ${DATA_DIR} ${LOG_DIR} ${RUN_DIR} ${SCRIPT_DIR}
chown -R ${VENDOR_USER}:`id --group --name ${VENDOR_USER}` ${VENDOR_DIR}

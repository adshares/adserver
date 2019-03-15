#!/usr/bin/env bash
set -e

[[ $# -ge 2 ]] || echo "Usage: `basename $0` <daemon_name> <source_dir> [<target_dir>]"

DAEMON_NAME="$1"
shift

SOURCE_DIR="$1"
shift

TARGET_DIR=${1:-"/etc/${DAEMON_NAME}/conf.d"}
test -z $1 || shift

DAEMON_SERVICE_NAME=${1:-${DAEMON_NAME}}
test -z $1 || shift

source $(dirname $(readlink -f "$0"))/_functions.sh root

echo "Remove ${SERVICE_NAME}-${DAEMON_NAME}-*.conf from ${DAEMON_NAME} (if any exist)"
find  ${TARGET_DIR} -maxdepth 1 -name "${SERVICE_NAME}-${DAEMON_NAME}-*.conf" -type f -delete

FILE_COUNT=$(find ${SOURCE_DIR} -maxdepth 1 -name "${DAEMON_NAME}*.conf" -type f -print | wc -l)
FILE_ITEMS=$(find ${SOURCE_DIR} -maxdepth 1 -name "${DAEMON_NAME}*.conf" -type f -print)

if [[ ${FILE_COUNT} -gt 0 ]]
then
    for FILE in ${FILE_ITEMS}
    do
        echo "Copy ${FILE} to ${TARGET_DIR}"
        [[ -e ${VENDOR_DIR}/${SERVICE_NAME}/.env ]] && cat ${VENDOR_DIR}/${SERVICE_NAME}/.env && set -a && source ${VENDOR_DIR}/${SERVICE_NAME}/.env && set +a
        envsubst < ${FILE} | tee ${TARGET_DIR}/${SERVICE_NAME}-$(basename ${FILE})
    done

    echo "Reload or (re)start ${DAEMON_SERVICE_NAME}"
    systemctl reload-or-restart ${DAEMON_SERVICE_NAME}
fi

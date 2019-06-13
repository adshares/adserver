#!/usr/bin/env bash

set -eux

_INTERVAL_DAYS=${1:-32}
SERVICE_DIR=$(dirname $(dirname $(readlink -f $0)))
SERVICE_NAME=$(basename ${SERVICE_DIR})
VENDOR_DIR=$(dirname ${SERVICE_DIR})
VENDOR_NAME=$(basename ${VENDOR_DIR})

BACKUP_DIR="${VENDOR_DIR}/.backup"

_DB="${VENDOR_NAME}_${SERVICE_NAME}"
_CREDENTIALS="--user=${VENDOR_NAME} --password=${VENDOR_NAME}"

__DATE=$(mysql ${_CREDENTIALS} -e "SELECT CURRENT_DATE - INTERVAL ${_INTERVAL_DAYS} DAY" --batch | tail -1)
_CONDITION="created_at < '${__DATE}'"

_TABLE="network_event_logs"
_FILE="${BACKUP_DIR}/${_TABLE}-$(date -u -Iseconds).sql"

mysqldump ${_CREDENTIALS} --no-tablespaces --no-create-db --no-create-info --where="${_CONDITION}" --result-file=${_FILE} ${_DB} ${_TABLE}
mysql ${_CREDENTIALS} --execute="DELETE FROM ${_TABLE} WHERE ${_CONDITION}" ${_DB}

_TABLE="event_logs"
_FILE="${BACKUP_DIR}/${_TABLE}-$(date -u -Iseconds).sql"

mysqldump ${_CREDENTIALS} --no-tablespaces --no-create-db --no-create-info --where="${_CONDITION}" --result-file=${_FILE} ${_DB} ${_TABLE}
mysql ${_CREDENTIALS} --execute="DELETE FROM ${_TABLE} WHERE ${_CONDITION}" ${_DB}

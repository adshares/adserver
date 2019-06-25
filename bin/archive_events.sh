#!/usr/bin/env bash

set -eu
source .env

_INTERVAL_DAYS=${1:-32}
SERVICE_DIR=$(dirname $(dirname $(readlink -f $0)))
SERVICE_NAME=$(basename ${SERVICE_DIR})
VENDOR_DIR=$(dirname ${SERVICE_DIR})
VENDOR_NAME=$(basename ${VENDOR_DIR})

BACKUP_DIR="${VENDOR_DIR}/.backup"

_DB="${DB_DATABASE}"
_CREDENTIALS="--user=${DB_USERNAME} --password=${DB_PASSWORD}"

__DATE=$(mysql ${_CREDENTIALS} -e "SELECT CURRENT_DATE - INTERVAL ${_INTERVAL_DAYS} DAY" --batch | tail -1)
_CONDITION="created_at < '${__DATE}'"

__TS=$(date -u -Iseconds)

# ===

_TABLE="network_event_logs"
_FILE="${BACKUP_DIR}/${_TABLE}-${__TS}-before_${__DATE}.sql"

mysqldump ${_CREDENTIALS} --no-tablespaces --no-create-db --no-create-info --where="${_CONDITION}" --result-file=${_FILE} ${_DB} ${_TABLE} && \
  tar -zcvf ${_FILE}.tar.gz ${_FILE} && \
  rm ${_FILE} && \
  mysql ${_CREDENTIALS} --execute="DELETE FROM ${_TABLE} WHERE ${_CONDITION}" ${_DB}

_TABLE="event_logs"
_FILE="${BACKUP_DIR}/${_TABLE}-${__TS}-before_${__DATE}.sql"

mysqldump ${_CREDENTIALS} --no-tablespaces --no-create-db --no-create-info --where="${_CONDITION}" --result-file=${_FILE} ${_DB} ${_TABLE} && \
  tar -zcvf ${_FILE}.tar.gz ${_FILE} && \
  rm ${_FILE} && \
  mysql ${_CREDENTIALS} --execute="DELETE FROM ${_TABLE} WHERE ${_CONDITION}" ${_DB}

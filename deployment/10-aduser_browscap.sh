#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=aduser

PROJECT_DATA_DIR="${INSTALLATION_DIR}/${SERVICE_NAME}_data"

if [[ ${INSTALL_BROWSCAP_DATA:-0} -eq 1 ]]
then
    mkdir -p ${PROJECT_DATA_DIR}

    TEMP_FILE="$(mktemp).zip"
    curl https://browscap.org/stream?q=BrowsCapZIP --silent --show-error -o ${TEMP_FILE}
    test -f ${PROJECT_DATA_DIR}/browscap.csv || unzip ${TEMP_FILE} browscap.csv -d ${PROJECT_DATA_DIR}
    rm ${TEMP_FILE}
fi

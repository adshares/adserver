#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=aduser

if [[ ${INSTALL_BROWSCAP_DATA:-0} -eq 1 ]]
then
    PROJECT_DATA_DIR="${INSTALLATION_DIR}/${SERVICE_NAME}_data"
    mkdir -p ${PROJECT_DATA_DIR}

    TEMP_FILE="$(mktemp).zip"
    curl https://browscap.org/stream?q=BrowsCapZIP --silent --show-error -o ${TEMP_FILE}
    test -f ${PROJECT_DATA_DIR}/browscap.csv || unzip ${TEMP_FILE} browscap.csv -d ${PROJECT_DATA_DIR}
    rm ${TEMP_FILE}
fi

cd ${INSTALLATION_DIR}/${SERVICE_NAME}/${SERVICE_NAME}_data_services

pipenv install

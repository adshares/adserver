#!/usr/bin/env bash

set -ex

VENDOR_NAME=adshares
PROJECT_NAME=aduser

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/${VENDOR_NAME}}

PROJECT_DATA_DIR="${INSTALLATION_DIR}/${PROJECT_NAME}_data"

if [[ ${INSTALL_BROWSCAP_DATA:-0} -eq 1 ]]
then
    mkdir -p ${PROJECT_DATA_DIR}

    TEMP_FILE="$(mktemp).zip"
    curl https://browscap.org/stream?q=BrowsCapZIP --silent --show-error -o ${TEMP_FILE}
    test -f ${PROJECT_DATA_DIR}/browscap.csv || unzip ${TEMP_FILE} browscap.csv -d ${PROJECT_DATA_DIR}
    rm ${TEMP_FILE}
fi

#screen -S ${PROJECT_NAME}_browscap -X quit || true
#cd ${INSTALLATION_DIR}/${PROJECT_NAME}/${PROJECT_NAME}_data_services
#pipenv install
#
#export PYTHONUNBUFFERED=1
#export ADUSER_DATA_BROWSCAP_SOCK_FILE=/tmp/${VENDOR_NAME}/${PROJECT_NAME}-data-browscap.sock
#export ADUSER_DATA_BROWSCAP_CSV_PATH=${PROJECT_DATA_DIR}/browscap.csv
#screen -S ${PROJECT_NAME}_browscap -dm bash -c "pipenv run python ${PROJECT_NAME}_data_services/browscap/daemon.py"

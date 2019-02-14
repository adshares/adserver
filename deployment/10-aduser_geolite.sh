#!/usr/bin/env bash

set -ex

VENDOR_NAME=adshares
PROJECT_NAME=aduser

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/${VENDOR_NAME}}

PROJECT_DATA_DIR="${INSTALLATION_DIR}/${PROJECT_NAME}_data"

if [[ ${INSTALL_GEOLITE_DATA:-0} -eq 1 ]]
then
    mkdir -p ${PROJECT_DATA_DIR}

    curl http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.tar.gz --silent --show-error \
      | tar --directory=${PROJECT_DATA_DIR} --gzip --extract --no-anchored --strip-components 1 'GeoLite2-City.mmdb'
fi

#screen -S ${PROJECT_NAME}_geolite -X quit || true
#cd ${INSTALLATION_DIR}/${PROJECT_NAME}/${PROJECT_NAME}_data_services
#pipenv install
#
#export PYTHONUNBUFFERED=1
#export ADUSER_DATA_GEOLITE_SOCK_FILE=/tmp/${VENDOR_NAME}/${PROJECT_NAME}-data-geolite.sock
#export ADUSER_DATA_GEOLITE_PATH=${PROJECT_DATA_DIR}/GeoLite2-City.mmdb
#screen -S ${PROJECT_NAME}_geolite -dm bash -c "pipenv run python ${PROJECT_NAME}_data_services/geolite/daemon.py"

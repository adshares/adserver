#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=aduser

PROJECT_DATA_DIR="${INSTALLATION_DIR}/${SERVICE_NAME}_data"

if [[ ${INSTALL_GEOLITE_DATA:-0} -eq 1 ]]
then
    mkdir -p ${PROJECT_DATA_DIR}

    curl http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.tar.gz --silent --show-error \
      | tar --directory=${PROJECT_DATA_DIR} --gzip --extract --no-anchored --strip-components 1 'GeoLite2-City.mmdb'
fi

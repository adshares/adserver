#!/usr/bin/env bash
set -e

HERE=$(dirname $(readlink -f "$0"))
INSTALLER_DIR=$(dirname $(dirname ${HERE}))/adserver/installer

if [[ $EUID -ne 0 ]]
then
    echo "You need to be root to run $0" >&2
    exit 1
fi

if [[ -z "$1" ]]
then
    SERVICES=$(cat ${INSTALLER_DIR}/services.txt)
else
    SERVICES="$1"
    if [[ "$SERVICES" == "-" ]]
    then
        SKIP_SERVICES=1
    fi
fi

if [[ -z "$2" ]]
then
    BRANCH=master
else
    BRANCH="$2"
fi

if [[ -z "$3" ]]
then
    INSTALLATION_USER=adshares
else
    INSTALLATION_USER="$3"
fi

set -x
TEMP_DIR=$(mktemp --directory)
cp -r ${INSTALLER_DIR}/* ${TEMP_DIR}

export SCRIPT_DIR="${TEMP_DIR}"

#${TEMP_DIR}/bootstrap.sh

if [[ ${SKIP_SERVICES:-0} -ne 1 ]]
then
    for SERVICE in ${SERVICES}
    do
        if [[ "$SERVICE" == "aduser-php" ]]
        then
            ${TEMP_DIR}/clone.sh aduser php
        else
            ${TEMP_DIR}/clone.sh ${SERVICE} ${BRANCH}
        fi
    done
fi

${TEMP_DIR}/prepare-directories.sh

if [[ ${SKIP_SERVICES:-0} -ne 1 ]]
then
    for SERVICE in ${SERVICES}
    do
        if [[ "$SERVICE" == "aduser-php" ]]
        then
            ${TEMP_DIR}/run-target.sh build /opt/adshares/aduser ${TEMP_DIR}/${SERVICE} ${INSTALLATION_USER}
        else
            ${TEMP_DIR}/run-target.sh build /opt/adshares/${SERVICE} /opt/adshares/${SERVICE}/deploy ${INSTALLATION_USER}

            if [[ "$SERVICE" == "aduser" ]]
            then
                ${TEMP_DIR}/run-target.sh build-browscap /opt/adshares/${SERVICE}/${SERVICE}_data_services ${TEMP_DIR}/${SERVICE} ${INSTALLATION_USER} 1
                ${TEMP_DIR}/run-target.sh build-geolite /opt/adshares/${SERVICE}/${SERVICE}_data_services ${TEMP_DIR}/${SERVICE} ${INSTALLATION_USER} 1
            fi
        fi

        ${TEMP_DIR}/configure-daemon.sh fpm ${TEMP_DIR}/${SERVICE} /etc/php/7.2/fpm/pool.d
        ${TEMP_DIR}/configure-daemon.sh nginx ${TEMP_DIR}/${SERVICE}
        ${TEMP_DIR}/configure-daemon.sh supervisor ${TEMP_DIR}/${SERVICE}
    done
fi

rm -rf ${TEMP_DIR}

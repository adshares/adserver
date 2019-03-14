#!/usr/bin/env bash
set -e

HERE=$(dirname $(readlink -f "$0"))
INSTALLER_DIR=$(dirname ${HERE})/installer

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
        SKIP_CLONE=1
    fi
    shift
fi

if [[ -z "$1" ]]
then
    BRANCH=master
else
    BRANCH="$1"
    shift
fi

if [[ -z "$1" ]]
then
    INSTALLATION_USER=adshares
else
    INSTALLATION_USER="$1"
    shift
fi

export SCRIPT_DIR=$(mktemp --directory)

cp -r ${INSTALLER_DIR}/* ${SCRIPT_DIR}

if [[ ${SKIP_BOOTSTRAP:-0} -ne 1 ]]
then
    ${SCRIPT_DIR}/bootstrap.sh
fi

if [[ ${SKIP_CLONE:-0} -ne 1 ]]
then
    for SERVICE in ${SERVICES}
    do
        if [[ "$SERVICE" == "aduser" ]]
        then
            ${SCRIPT_DIR}/clone.sh ${SERVICE} deploy
        elif [[ "$SERVICE" == "adserver" ]]
        then
            ${SCRIPT_DIR}/clone.sh ${SERVICE} deploy
        elif [[ "$SERVICE" == "adpanel" ]]
        then
            ${SCRIPT_DIR}/clone.sh ${SERVICE} deploy
        else
            ${SCRIPT_DIR}/clone.sh ${SERVICE} ${BRANCH}
        fi
    done
fi

${SCRIPT_DIR}/prepare-directories.sh

export DEBUG_MODE=1

if [[ ${SKIP_CONFIGURE:-0} -ne 1 ]]
then
    sudo --preserve-env --user=${INSTALLATION_USER} ${SCRIPT_DIR}/configure.sh
fi

if [[ ${SKIP_SERVICES:-0} -ne 1 ]]
then
    for SERVICE in ${SERVICES}
    do
        export SERVICE_NAME=${SERVICE}
        ${SCRIPT_DIR}/run-target.sh build /opt/adshares/${SERVICE} /opt/adshares/${SERVICE}/deploy ${INSTALLATION_USER} ${SCRIPT_DIR} /opt/adshares/${SERVICE}

        ${SCRIPT_DIR}/configure-daemon.sh nginx /opt/adshares/${SERVICE}/deploy
        ${SCRIPT_DIR}/configure-daemon.sh supervisor /opt/adshares/${SERVICE}/deploy
    done
fi

${SCRIPT_DIR}/configure-daemon.sh fpm ${SCRIPT_DIR} /etc/php/7.2/fpm/pool.d php7.2-fpm

rm -rf ${SCRIPT_DIR}

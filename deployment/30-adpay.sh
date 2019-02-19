#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=adpay

source ${HERE}/clone-service.sh

cd ${INSTALLATION_DIR}/${SERVICE_NAME}

pipenv install

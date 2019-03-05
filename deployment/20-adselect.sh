#!/usr/bin/env bash

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh

SERVICE_NAME=adselect

pipenv install

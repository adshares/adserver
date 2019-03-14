#!/usr/bin/env bash
set -e

echo "$*"

[[ $# -ge 2 ]] || echo "Usage: `basename $0` <target> <workdir> [[[<SCRIPT_DIR>] <sudo_as>] ...]"
TARGET="$1"
shift

WORKDIR="$1"
shift

SCRIPT_DIR="$1"
test -z $1 || shift

SUDO_AS="$1"
test -z $1 || shift

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh any

if [[ -z ${SUDO_AS} ]] || [[ `id --user --name` == ${SUDO_AS} ]]
then
    cd ${WORKDIR}
    ${SCRIPT_DIR}/${TARGET}.sh $@
else
    sudo --login --preserve-env --user=${SUDO_AS} "`env`" ${SCRIPT_DIR}/${TARGET}.sh $@
fi

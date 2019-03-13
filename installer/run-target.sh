#!/usr/bin/env bash
set -e

[[ $# -ge 2 ]] || echo "Usage: `basename $0` <target> <workdir> [[[<SCRIPT_DIR>] <sudo_as>] ...]"
TARGET="$1"
WORKDIR="$2"

shift
shift

SCRIPT_DIR="$1"
SUDO_AS="$2"

test -z $1 || shift
test -z $1 || shift

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh root

if [[ -z ${SUDO_AS} ]]
then
    cd ${WORKDIR}
    ${SCRIPT_DIR}/${TARGET}.sh $@
else
    sudo --login --user=${SUDO_AS} bash --login -c "cd ${WORKDIR}; ${SCRIPT_DIR}/${TARGET}.sh $@"
fi

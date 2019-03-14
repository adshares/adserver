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
echo "1: $0"
env | sort | grep SKIP_ || echo "NO SKIP_..."
    cd ${WORKDIR}
    ${SCRIPT_DIR}/${TARGET}.sh $@
else
echo "2: $0"
env | sort | grep SKIP_ || echo "NO SKIP_..."
#    sudo --preserve-env --login --user=${SUDO_AS} $(readlink -f "$0") ${TARGET} ${WORKDIR} ${SCRIPT_DIR} ${SUDO_AS} $@
    sudo --login --preserve-env --user=${SUDO_AS} ${SCRIPT_DIR}/${TARGET}.sh $@
fi

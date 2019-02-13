#!/usr/bin/env bash

if [[ "$1" == "" ]]
then
    echo "Provide remote server name (for ssh & scp)"
    exit 1
else
    REMOTE_HOST="$1"
fi

set -ex

INSTALLATION_USER=${INSTALLATION_USER:-"adshares"}

HERE=$(dirname $(readlink -f "$0"))
THERE=$(dirname ${HERE})/deployment

# ===

REMOTE_TEMP_DIR=$(ssh ${REMOTE_HOST} "bash -c 'mktemp --directory'")
scp -r ${THERE}/* ${REMOTE_HOST}:${REMOTE_TEMP_DIR}

# ===

ssh ${REMOTE_HOST} sudo --login "bash --login -c 'INSTALLATION_USER=${INSTALLATION_USER} ${REMOTE_TEMP_DIR}/bootstrap.sh'"

# ===

ssh ${REMOTE_HOST} sudo --login "bash --login -c 'ls -la ${REMOTE_TEMP_DIR}'"

cd ${THERE}
for SCRIPT in `ls ?0-*.sh`
do
    SUPERVISED_SERVICE=`expr match "$SCRIPT" '.*-\(.*\)\.sh'`
    ssh ${REMOTE_HOST} sudo --login supervisorctl stop ${SUPERVISED_SERVICE}
    ssh ${REMOTE_HOST} sudo --login --user=${INSTALLATION_USER} "bash --login $REMOTE_TEMP_DIR/$SCRIPT"
    ssh ${REMOTE_HOST} sudo --login supervisorctl start ${SUPERVISED_SERVICE}
done

# ===

ssh ${REMOTE_HOST} sudo --login "bash --login -c 'rm -rf ${REMOTE_TEMP_DIR}'"

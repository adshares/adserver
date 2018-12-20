#!/usr/bin/env bash

set -e

# Create installation directory
mkdir -p ${INSTALLATION_PATH}

# Move directories
mv * ${INSTALLATION_PATH}/

mkdir -pm 777 ${INSTALLATION_PATH}/storage

#cd ${INSTALLATION_PATH}
#./bin/init.sh --stop
#./bin/init.sh --build --migrate --seed --start

#!/usr/bin/env bash

set -e

# Create directories
mkdir -p ${INSTALLATION_PATH}
mkdir -pm 777 ${INSTALLATION_PATH}/storage

# Move directories
mv app ${INSTALLATION_PATH}/
mv bin ${INSTALLATION_PATH}/
mv bootstrap ${INSTALLATION_PATH}/
mv config ${INSTALLATION_PATH}/
mv database ${INSTALLATION_PATH}/
mv node_modules ${INSTALLATION_PATH}/
mv public ${INSTALLATION_PATH}/
mv resources ${INSTALLATION_PATH}/
mv routes ${INSTALLATION_PATH}/
mv src ${INSTALLATION_PATH}/
mv tests ${INSTALLATION_PATH}/
mv vendor ${INSTALLATION_PATH}/

mv composer.json ${INSTALLATION_PATH}/

# Move artisan binary
mv artisan ${INSTALLATION_PATH}/

# Migrate
cd ${INSTALLATION_PATH}
./bin/init.sh --migrate --force --seed

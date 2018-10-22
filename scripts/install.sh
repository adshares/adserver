#!/usr/bin/env bash

# Create directories
mkdir -p ${ADSERVER_INSTALLATION_DIR}
mkdir -m 777 ${ADSERVER_INSTALLATION_DIR}/storage

# Move directories
mv app ${ADSERVER_INSTALLATION_DIR}/
mv bin ${ADSERVER_INSTALLATION_DIR}/
mv bootstrap ${ADSERVER_INSTALLATION_DIR}/
mv config ${ADSERVER_INSTALLATION_DIR}/
mv database ${ADSERVER_INSTALLATION_DIR}/
mv node_modules ${ADSERVER_INSTALLATION_DIR}/
mv public ${ADSERVER_INSTALLATION_DIR}/
mv resources ${ADSERVER_INSTALLATION_DIR}/
mv routes ${ADSERVER_INSTALLATION_DIR}/
mv tests ${ADSERVER_INSTALLATION_DIR}/
mv vendor ${ADSERVER_INSTALLATION_DIR}/

# Move artisan binary
mv artisan ${ADSERVER_INSTALLATION_DIR}/

# Migrate
cd ${ADSERVER_INSTALLATION_DIR}
./artisan migrate

#!/usr/bin/env bash

set -ex

# Create installation directory
mkdir -p ${INSTALLATION_PATH}

# Move directories
mv * ${INSTALLATION_PATH}/
mv .env* ${INSTALLATION_PATH}/

mkdir -pm 777 ${INSTALLATION_PATH}/storage

cd ${INSTALLATION_PATH}
./artisan migrate:fresh
./artisan db:seed

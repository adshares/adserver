#!/usr/bin/env bash

set -ex

# Create installation directory
mkdir -p ${INSTALLATION_PATH}

# Move directories
mv * ${INSTALLATION_PATH}/

cd ${INSTALLATION_PATH}
./artisan migrate:fresh
./artisan db:seed

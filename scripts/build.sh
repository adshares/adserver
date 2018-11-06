#!/usr/bin/env bash

set -e

env | sort

if [ ! -v TRAVIS ]; then
  # Checkout repo and change directory

  # Install git
  git --version || apt-get install -y git

  git clone \
    --depth=1 \
    https://github.com/adshares/adserver.git \
    --branch ${ADSERVER_BRANCH} \
    ${BUILD_PATH}/build

  cd ${BUILD_PATH}/build
fi

envsubst < .env.dist | tee .env

composer install --${APP_ENV}

./artisan key:generate
./artisan package:discover

yarn install
yarn run ${APP_ENV}

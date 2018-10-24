#!/usr/bin/env bash

env | sort

if [ ! -v TRAVIS ]; then
  # Checkout repo and change directory

  # Install git
  git --version || apt-get install -y git

  git clone \
    --depth=1 \
    https://github.com/adshares/adserver.git \
    --branch ${ADSERVER_INSTALLATION_BRANCH} \
    ${ADSERVER_BUILD_PATH}/build

  cd ${ADSERVER_BUILD_PATH}/build
fi

envsubst < .env.dist | tee .env

composer install --${APP_ENV}

./artisan key:generate
./artisan package:discover

yarn install
yarn run ${APP_ENV}

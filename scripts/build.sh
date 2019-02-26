#!/usr/bin/env bash

set -ex

if [[ -v GIT_CLONE ]]
then
  # Checkout repo and change directory

  # Install git
  git --version || apt-get install -y git

  git clone \
    --depth=1 \
    https://github.com/adshares/adserver.git \
    --branch ${BUILD_BRANCH:-master} \
    ${BUILD_PATH}/build

  cd ${BUILD_PATH}/build
fi
env
sleep 100000
composer install

yarn install
yarn run ${APP_ENV}

mkdir -p storage/app/public/banners
chmod a+rwX -R storage

envsubst < info.json.template | tee public/info.json

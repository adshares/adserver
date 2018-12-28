#!/usr/bin/env bash

set -ex

env | sort

if [ ! -v TRAVIS ]; then
  # Checkout repo and change directory

  # Install git
  git --version || apt-get install -y git

  git clone \
    --depth=1 \
    https://github.com/adshares/adserver.git \
    --branch ${BUILD_BRANCH:-master} \
    ${BUILD_PATH}/build

  cd ${BUILD_PATH}/build

  composer install --no-dev
  ./artisan config:cache
else
    composer install
fi

yarn install
yarn run dev

./artisan package:discover

mkdir -p storage/app/public/banners
chmod a+rwX -R storage

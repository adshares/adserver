#!/usr/bin/env bash

set -e

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

composer install

yarn install
yarn run ${APP_ENV}

mkdir -p storage/app/public/banners
chmod a+rwX -R storage

GIT_TAG=$(git tag -l --points-at HEAD | head -n 1)
GIT_HASH="#"$(git rev-parse --short HEAD)

APP_VERSION=${APP_VERSION:-${GIT_TAG:-${GIT_HASH}}}

echo "APP_VERSION=$APP_VERSION" | tee .env.from-build


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

  ${BUILD_PATH}/bin/init.sh --build
fi


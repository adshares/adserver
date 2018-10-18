#!/usr/bin/env bash

if [ ! -v TRAVIS ]; then
  # Checkout repo and change directory

  # Install git
  apt-get install -y git

  git clone https://github.com/adshares/adserver.git --branch $BUILD_BRANCH --single-branch /build/adserver
  cd /build/adserver
fi

envsubst < .env.dist | tee .env

composer install --dev

./artisan key:generate
./artisan package:discover

yarn install
yarn run dev

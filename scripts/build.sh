#!/usr/bin/env bash

if [ ! -z "$TRAVIS" ]; then
  # Checkout repo and change directory

  # Install git
  apt-get install -y git

  git clone https://github.com/adshares/adserver.git /build/adserver
  cd /build/adserver
fi

envsubst < .env.dist | tee .env

composer install --dev

./artisan key:generate
./artisan package:discover

yarn install
yarn run dev

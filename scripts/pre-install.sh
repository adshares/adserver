#!/usr/bin/env bash

set -e

apt-get -qq -y  --no-install-recommends install \
        php7.2-fpm php7.2-mysql php7.2-bcmath php7.2-bz2 php7.2-cli php7.2-curl \
        php7.2-gd php7.2-intl php7.2-json php7.2-mbstring php7.2-opcache \
        php7.2-readline php7.2-sqlite3 php7.2-zip

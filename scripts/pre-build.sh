#!/usr/bin/env bash

# Ubuntu 18.04 only

# Install dependencies for composer operations
apt-get install -y php7.2-cli php7.2-curl php7.2-zip php7.2-xdebug php7.2-mysql unzip

# Get composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '93b54496392c062774670ac18b134c3b3a95e5a5e5c8f1a9f115f203b75bf9a129d5daa8ba6a13e2cc8a1da0806388a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

# Install composer
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"

# Install composer plugin for faster operations
composer global require hirak/prestissimo

# Install dependencies for yarn operations
apt-get install -y nodejs npm

# Get yarn
apt-get install -y curl

curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

apt-get remove -y curl

# Install yarn
apt-get update && apt-get install -y yarn

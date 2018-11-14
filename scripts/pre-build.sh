#!/usr/bin/env bash

# Ubuntu 18.04 only
set -e

# Install dependencies for composer operations
apt-get -qq -y install \
        php7.2-cli php7.2-mysql php7.2-bcmath php7.2-bz2 php7.2-cli php7.2-curl \
        php7.2-gd php7.2-intl php7.2-json php7.2-mbstring php7.2-opcache \
        php7.2-readline php7.2-sqlite3 php7.2-xml php7.2-xmlrpc php7.2-xsl php7.2-zip

composer --version || export INSTALL_COMPOSER=true
nodejs --version || export INSTALL_NODEJS=true
npm --version || export INSTALL_NPM=true
yarn --version || export INSTALL_YARN=true

if [ -v INSTALL_COMPOSER ]; then
    # Get composer
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '93b54496392c062774670ac18b134c3b3a95e5a5e5c8f1a9f115f203b75bf9a129d5daa8ba6a13e2cc8a1da0806388a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

    # Install composer
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"

    # Install composer plugin for faster operations
    composer global require hirak/prestissimo
fi

# Install dependencies for yarn operations
if [ -v INSTALL_NODEJS ]; then
    PACKAGE_LIST="${PACKAGE_LIST:-""} nodejs"
fi

if [ -v INSTALL_NPM ]; then
    PACKAGE_LIST="${PACKAGE_LIST:-""} npm"
fi

if [ -v INSTALL_YARN ]; then
    # Get yarn
    php -r "copy('https://dl.yarnpkg.com/debian/pubkey.gpg', 'yarn.pubkey.gpg');"
    cat yarn.pubkey.gpg | apt-key add -
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

    PACKAGE_LIST="${PACKAGE_LIST:-""} yarn"
fi

# Install yarn
apt-get -qq -y update && apt-get -qq -y install $PACKAGE_LIST

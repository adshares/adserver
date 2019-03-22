#!/usr/bin/env bash

set -e
apt-get update

apt-get -qq -y --no-install-recommends install \
        php7.2-cli php7.2-mysql php7.2-bcmath php7.2-bz2 php7.2-cli php7.2-curl \
        php7.2-gd php7.2-intl php7.2-json php7.2-mbstring php7.2-opcache \
        php7.2-readline php7.2-sqlite3 php7.2-zip php7.2-xml php-apcu

echo "apc.enable_cli=1" >> /etc/php/7.2/cli/php.ini

composer --version || export INSTALL_COMPOSER=true
nodejs --version || export INSTALL_NODEJS=true
npm --version || export INSTALL_NPM=true
export INSTALL_YARN=true

if [ -v INSTALL_COMPOSER ]; then
    # Get composer
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

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
apt-get -qq -y update && apt-get -qq -y install  --no-install-recommends $PACKAGE_LIST

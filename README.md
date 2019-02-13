<p align="center">
    <a href="https://adshares.net/" title="Adshares sp. z o.o." target="_blank">
        <img src="https://adshares.net/logos/ads.svg" alt="Adshares" width="100" height="100">
    </a>
</p>
<h3 align="center"><small>Adshares / AdServer</small></h3>
<p align="center">
    <a href="https://github.com/adshares/adserver/issues/new?template=bug_report.md&labels=Bug">Report bug</a>
    ·
    <a href="https://github.com/adshares/adserver/issues/new?template=feature_request.md&labels=New%20Feature">Request feature</a>
    ·
    <a href="https://github.com/adshares/adserver/wiki">Wiki</a>
</p>
<p align="center">
    <a href="https://travis-ci.org/adshares/adserver" title="Build Status" target="_blank">
        <img src="https://travis-ci.org/adshares/adserver.svg?branch=master" alt="Build Status">
    </a>
    <a href="https://sonarcloud.io/dashboard?id=adshares-adserver" title="Code Quality" target="_blank">
        <img src="https://sonarcloud.io/api/project_badges/measure?project=adshares-adserver&metric=alert_status" alt="Code Quality">
    </a>
</p>

AdServer is the core software behind the ecosystem.

## Quick Start (on Ubuntu 18.04)

> Requirements:
> - [Nodejs](https://nodejs.org/en/) 
> - [yarn](https://yarnpkg.com/en/) (or at least npm)
> - [Composer](https://getcomposer.org/) - Dependency Manager for PHP
> - A HTTP Server of your choice (eg. Nginx, see: [nginx.conf](docker/nginx.conf))
> - Mysql (or similar) database

### Install dependencies
```bash
curl https://dl.yarnpkg.com/debian/pubkey.gpg -sS | sudo apt-key add - && echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list
sudo add-apt-repository --yes ppa:adshares/releases
sudo apt-get --yes --no-install-recommends install php7.2-fpm php7.2-mysql php7.2-bcmath php7.2-bz2 php7.2-curl php7.2-gd php7.2-intl php7.2-mbstring php7.2-sqlite3 php7.2-zip php7.2-simplexml gettext-base screen ads nginx mysql-server nodejs yarn unzip

#### Composer
test $(curl https://getcomposer.org/installer -sS | sha384sum | head -c 96) == "48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5" && \
curl https://getcomposer.org/installer -sS | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

Clone and ...
```bash
git clone https://github.com/adshares/adserver.git && cd adserver
composer install

yarn install
yarn run prod

mkdir -pm 777 storage/app/public/banners
```

### Configure Environment
```bash
export APP_NAME=AdServer
export APP_ENV=production
export APP_DEBUG=false
export APP_URL=http://localhost:8101 # publicly visible AdServer URL 
export APP_KEY=base64:`date | sha256sum | head -c 32 | base64`

export LOG_CHANNEL=single

export DB_HOST=database
export DB_PORT=3306
export DB_DATABASE=adserver
export DB_USERNAME=adserver
export DB_PASSWORD=adserver

export BROADCAST_DRIVER=log
export CACHE_DRIVER=file
export SESSION_DRIVER=file

export SESSION_LIFETIME=120

export QUEUE_DRIVER=database

export MAIL_DRIVER=smtp # for testing purposes 'log` can be used
export MAIL_HOST=mailer
export MAIL_PORT=1025
export MAIL_USERNAME=1025
export MAIL_PASSWORD=
export MAIL_ENCRYPTION=null
export MAIL_FROM_ADDRESS=dev@adshares.net
export MAIL_FROM_NAME="[dev] AdShares"

export ADSERVER_SECRET=5LM0pJKnAlXDwSwSSqyJt
export ADSERVER_ID=AdShrek
export ADSERVER_HOST=http://localhost:8101
export ADSERVER_BANNER_HOST=http://localhost:8101

export ADSHARES_ADDRESS=0000-00000000-XXXX # account number (hot wallet) to be used by the server 
export ADSHARES_NODE_HOST=t01.e11.click # account's node hostname
export ADSHARES_NODE_PORT=6511
export ADSHARES_SECRET= # account's secret key
export ADSHARES_COMMAND=`which ads`
export ADSHARES_WORKINGDIR=/tmp/adshares/ads-cache

export ADUSER_EXTERNAL_LOCATION=http://localhost:8010 # publicly visible AdServer URL
export ADUSER_INTERNAL_LOCATION=http://localhost:8010 # locally visible AdServer URL

export ADSELECT_ENDPOINT=http://localhost:8011 # locally visible AdSelect URL

export ADPAY_ENDPOINT=http://localhost:8012 # locally visible AdPay URL

export ADPANEL_URL=http://localhost # publicly visible AdPanel URL
```

## Documentation

- [Wiki](https://github.com/adshares/adserver/wiki)
- [Changelog](CHANGELOG.md)
- [Contributing Guidelines](docs/CONTRIBUTING.md)
- [Authors](https://github.com/adshares/adserver/contributors)
- Available [Versions](https://github.com/adshares/adserver/tags) (we use [Semantic Versioning](http://semver.org/))

### Related projects


## Related projects

- [AdUser](https://github.com/adshares/aduser)
- [AdSelect](https://github.com/adshares/adselect)
- [AdPay](https://github.com/adshares/adpay)
- [AdPanel](https://github.com/adshares/adpanel)
- [ADS](https://github.com/adshares/ads)

## License

This work is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This work is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
[GNU General Public License](LICENSE) for more details.

You should have received a copy of the License along with this work.
If not, see <https://www.gnu.org/licenses/gpl.html>.

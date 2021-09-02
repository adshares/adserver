#!/usr/bin/env bash
#
# Copyright (c) 2018-2021 Adshares sp. z o.o.
#
# This file is part of AdServer
#
# AdServer is free software: you can redistribute and/or modify it
# under the terms of the GNU General Public License as published
# by the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# AdServer is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty
# of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with AdServer. If not, see <https://www.gnu.org/licenses/>
#

# Usage: build.sh [<work-dir>]
cd ${1:-"."}

function artisanCommand {
    ./artisan --no-interaction "$@"
}

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners
mkdir -pm 777 storage/framework/cache
mkdir -pm 777 storage/framework/sessions
mkdir -pm 777 storage/framework/views

echo 'user_id;network_banner_uuid' > storage/app/reported-ads.txt
chmod 664 storage/app/reported-ads.txt

ln -sf ${SERVICE_DIR}/storage/app/public public/storage

composer install --no-dev --optimize-autoloader

yarn global add cross-env
yarn install
yarn run prod

rm -f bootstrap/cache/config.php
artisanCommand optimize

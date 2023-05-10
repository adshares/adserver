#!/usr/bin/env bash
#
# Copyright (c) 2018-2022 Adshares sp. z o.o.
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
WORK_DIR=${1:-"."}
cd "$WORK_DIR" || exit 1

function artisanCommand {
    ./artisan --no-interaction "$@"
}

mkdir -p storage/app/invoices
mkdir -p storage/app/public/{banners,reports}
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/wallet
chmod 777 storage -R

echo 'user_id;network_banner_uuid' > storage/app/reported-ads.txt
chmod 664 storage/app/reported-ads.txt

ln -sf "${SERVICE_DIR}"/storage/app/public public/storage

composer install --no-dev --optimize-autoloader
if [ $? -ne 0 ]; then exit 1; fi

yarn global add cross-env
yarn install
if [ $? -ne 0 ]; then exit 1; fi

yarn run prod
if [ $? -ne 0 ]; then exit 1; fi

rm -f bootstrap/cache/config.php
artisanCommand optimize
if [ $? -ne 0 ]; then exit 1; fi

deploy/reload.sh

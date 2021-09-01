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

# Usage: migrate.sh [<work-dir>]
cd ${1:-"."}

function artisanCommand {
    ./artisan --no-interaction "$@"
}

if [[ ${DB_MIGRATE_FRESH:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh
elif [[ ${DB_MIGRATE_FRESH_FORCE:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh --force
elif [[ ${DB_MIGRATE_FRESH_FORCE_SEED:-0} -eq 1 ]]
then
    artisanCommand migrate:fresh --force --seed
elif [[ ${SKIP_DB_MIGRATE:-0} -eq 0 ]]
then
    artisanCommand migrate
fi

if [[ ${_DB_SEED:-0} -eq 1 ]]
then
    artisanCommand db:seed
fi

if [[ ${_UPDATE_TARGETING:-0} -eq 1 ]]
then
    artisanCommand ops:targeting-options:update
fi

if [[ ${_UPDATE_FILTERING:-0} -eq 1 ]]
then
    artisanCommand ops:filtering-options:update
fi

if [[ ${_UPDATE_NETWORK_HOSTS:-0} -eq 1 ]]
then
    artisanCommand ads:fetch-hosts --quiet
fi

if [[ ${_BROADCAST_SERVER:-0} -eq 1 ]]
then
    artisanCommand ads:broadcast-host
fi

if [[ ${_CREATE_ADMIN:-0} -eq 1 ]]
then
    artisanCommand ops:admin:create --password
fi

artisanCommand ops:exchange-rate:fetch

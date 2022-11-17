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

# Usage: migrate.sh [<work-dir>]
WORK_DIR=${1:-"."}
cd "$WORK_DIR" || exit 1

./artisan migrate --no-interaction --force || exit 1

if [ ! -f config/jwt/oauth-private.key ] || [ ! -f config/jwt/oauth-public.key ]
then
  ./artisan passport:keys --force || exit 1
  OUTPUT=$(./artisan passport:client --personal --name="Personal Access Client")
  CLIENT_ID=$(echo "$OUTPUT" | grep -oP "Client ID: \K\S+")
  CLIENT_SECRET=$(echo "$OUTPUT" | grep -oP "Client secret: \K\S+")
  if [ -z "$CLIENT_ID" ]; then echo "Missing CLIENT_ID"; exit 1; fi
  if [ -z "$CLIENT_SECRET" ]; then echo "Missing CLIENT_CLIENT_SECRET"; exit 1; fi
  echo "PASSPORT_PERSONAL_ACCESS_CLIENT_ID='$CLIENT_ID'" >> .env
  echo "PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET='$CLIENT_SECRET'" >> .env
fi

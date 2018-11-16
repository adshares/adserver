#!/usr/bin/env bash

set -e

maxcounter=45
counter=1

source .env

while ! mysql -h"$DB_HOST" -p"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "show databases;" > /dev/null 2>&1
do
    >&2 echo "DB not ready yet... sleep #$counter/$maxcounter"

    sleep 1

    counter=`expr $counter + 1`

    if [ $counter -gt $maxcounter ]; then
        >&2 echo "Database ($DB_HOST) down; Failing."
        exit 1
    fi
done

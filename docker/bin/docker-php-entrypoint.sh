#!/usr/bin/env bash

set -e

[ ${DEBUG:-0} -eq 1 ] && set -x

FOUND_OPTION=0
ARGS=("$@")
XDEBUG=${XDEBUG:-0}
DB_WAIT=0

while [ "$1" != "" ] && [ "${1#-}" != "$1" ]
do
    case "$1" in
        --dynhost )
            append_to_hosts.sh "$2" "$3"
            FOUND_OPTION=1;shift;shift
            ;;
        --keep-xdebug )
            XDEBUG=1
            FOUND_OPTION=1
            ;;
        --wait-for-db )
            DB_WAIT=1
            FOUND_OPTION=1
            ;;
        -- )
            FOUND_OPTION=1;shift;break
            ;;
    esac
    shift
done

echo "EUID: $EUID"

[ ${FOUND_OPTION} -eq 1 ] || set -- "${ARGS[@]}"

if [ $EUID -eq 0 ]
then
    [ "$XDEBUG" == "1" ] || phpdismod xdebug || echo "Xdebug already disabled."

    mkdir -p /run/php

    set_config_from_env.sh "PHP__"  "${PHP_ETC_DIR}/cli/conf.d/40-from-env.ini" "="
    set_config_from_env.sh "PHP__"  "${PHP_ETC_DIR}/fpm/conf.d/40-from-env.ini" "="

    if [ "${SSH_AUTHORIZED_KEY}" != "" ]
    then
        for authFile in `find / -mindepth 3 -maxdepth 4 -type f -name "authorized_keys"`
        do
            echo "Appending ssh-key to '$authFile':"
            echo -e "${SSH_AUTHORIZED_KEY}" | tee --append ${authFile}
        done
    fi
fi

[ "$DB_WAIT" == "0" ] || wait_for_database.sh

echo "=> $@"
exec "$@"

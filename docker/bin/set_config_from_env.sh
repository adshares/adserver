#!/usr/bin/env bash

# Credit: https://github.com/alterway/docker-php/blob/master/7.1-fpm/docker-entrypoint.sh

set -e

IFS_BACKUP=$IFS

echo -e "\n___ $2 ___\n"

#echo "$4" | tee "$2"

IFS=$(echo -en "\n\b")
for c in `printenv|grep $1`
do
    echo "`echo $c|cut -d "=" -f1|awk -F"$1" '{print $2}'` $3 `echo $c|cut -d "=" -f2`" | tee --append "$2"
done

echo -e "\n--- $2 ---\n"

IFS=$IFS_BACKUP

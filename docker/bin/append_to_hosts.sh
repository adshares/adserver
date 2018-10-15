#!/usr/bin/env bash

set -e

RESPONSE=$(host -t A $1);CODE=$?
if [ ${CODE} -eq 0 ]
then
    echo -e "`echo ${RESPONSE} | awk -F' ' '{print$4}'`\t$2" | tee --append /etc/hosts;
else
    echo "Could not find IP for '$1' to set as '$2'"
fi

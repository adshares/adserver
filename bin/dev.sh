#!/usr/bin/env bash

exec docker exec -it --user 1000 adshares_adserver_1 "$@"

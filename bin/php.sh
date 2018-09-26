#!/usr/bin/env bash

docker-compose exec --user 1000 dev php "$@"

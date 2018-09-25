#!/usr/bin/env bash

docker-compose exec --user 1000 php php "$@"

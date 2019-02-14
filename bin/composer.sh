#!/usr/bin/env bash

exec docker-compose run --rm worker composer "$@"

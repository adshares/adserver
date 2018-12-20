#!/usr/bin/env bash

exec docker-compose exec worker composer "$@"

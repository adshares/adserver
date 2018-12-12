#!/usr/bin/env bash
set -e
if [ ! -e ops_inventory_in_progress ]; then
    touch ops_inventory_in_progress

    ./artisan ops:inventory:import
    ./artisan ops:inventory:export

    rm -f ops_inventory_in_progress
fi

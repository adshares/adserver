#!/usr/bin/env bash
set -e
if [ ! -e ops_inventory_in_progress ]; then
    touch ops_inventory_in_progress

    ./artisan ops:demand:inventory:import
    ./artisan ops:adselect:inventory:export

    rm -f ops_inventory_in_progress
fi

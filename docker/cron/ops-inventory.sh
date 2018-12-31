#!/usr/bin/env bash

if [ ! -e /tmp/ops_inventory_in_progress ]; then
    touch /tmp/ops_inventory_in_progress

    ./artisan ops:demand:inventory:import
    ./artisan ops:adselect:inventory:export

    rm -f /tmp/ops_inventory_in_progress
fi

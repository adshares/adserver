#!/usr/bin/env bash

if [ ! -e /tmp/ops_inventory_in_progress ]; then
    touch /tmp/ops_inventory_in_progress

    ./artisan ops:adselect:event:export

    rm -f /tmp/ops_inventory_in_progress
fi

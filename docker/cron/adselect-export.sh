#!/usr/bin/env bash

if [ ! -e /tmp/adselect_export_in_progress ]; then
    touch /tmp/adselect_export_in_progress

    ./artisan ops:adselect:case:export

    rm -f /tmp/adselect_export_in_progress
fi

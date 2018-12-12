#!/usr/bin/env bash

if [ ! -e adpay_campaign_export_in_progress ]; then
    touch adpay_campaign_export_in_progress

    ./artisan adpay:campaign:export

    rm -f adpay_campaign_export_in_progress
fi

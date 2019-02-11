#!/usr/bin/env bash
PIDFILE=/tmp/ops_wallet_in_progress.pid

if [ ! -f $PIDFILE ]
then
    touch $PIDFILE

    ./artisan ops:wallet:transfer:cold
    ./artisan ops:wallet:transfer:check

    rm -f $PIDFILE
fi

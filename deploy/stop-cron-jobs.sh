#!/usr/bin/env bash
source ${1}/_functions.sh --root
[[ -z ${2:-""} ]] || cd $2

crontab -u ${VENDOR_USER} -r

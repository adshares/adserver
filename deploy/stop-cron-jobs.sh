#!/usr/bin/env bash
source ${1}/_functions.sh --root
[[ -z ${2:-""} ]] || cd $2
[[ ${DRY_RUN:-0} -eq 1 ]] && echo "DRY-RUN: $0 $@"

[[ ${DRY_RUN:-0} -eq 1 ]] || crontab -u ${VENDOR_USER} -r

#!/usr/bin/env bash

WORK_DIR=${1:-"."}
cd "$WORK_DIR" || exit 1

source .env.dist
source .env

DEFAULT_FAVICON=public/panel-assets-default/favicon.ico
CURRENT_FAVICON=public/favicon.ico

cp $DEFAULT_FAVICON $CURRENT_FAVICON

if [[ -n ${BRAND_ASSETS_DIR:-""} ]]
then
  if [[ -d ${BRAND_ASSETS_DIR} ]]
  then
    echo "Copying brand assets from ${BRAND_ASSETS_DIR}"
    NEW_FAVICON=$(find ${BRAND_ASSETS_DIR} -type f -name "favicon-*" | sort -t '\0' -n | tail -1)
    if [[ -n $NEW_FAVICON ]]
    then
      cp $NEW_FAVICON $CURRENT_FAVICON
    fi
  else
    echo "Brand assets directory ${BRAND_ASSETS_DIR} doesn't exist."
  fi
fi

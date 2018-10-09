#!/usr/bin/env bash

set -e

OPT_CLEAN=0
OPT_FORCE=0
OPT_BUILD=0
OPT_RUN=0
OPT_MIGRATE=0
OPT_MIGRATE_FRESH=0
OPT_LOGS=0
OPT_LOGS_FOLLOW=0

while [ "$1" != "" ]
do
    case "$1" in
        --clean )
            OPT_CLEAN=1
            OPT_FORCE=1
        ;;
        --force )
            OPT_FORCE=1
        ;;
        --build )
            OPT_BUILD=1
        ;;
        --run )
            OPT_RUN=1
        ;;
        --migrate )
            OPT_MIGRATE=1
        ;;
        --migrate-fresh )
            OPT_MIGRATE=1
            OPT_MIGRATE_FRESH=1
        ;;
        --logs )
            OPT_LOGS=1
        ;;
        --logs-follow )
            OPT_LOGS=1
            OPT_LOGS_FOLLOW=1
        ;;
    esac
    shift
done

if [ ${OPT_CLEAN} -eq 1 ]
then
    echo " > Destroy containers"
    docker-compose down && echo " < DONE" || echo " < INFO: Containers already down"
fi

if [ ${OPT_FORCE} -eq 1 ]
then
    rm -f .env
fi

# Docker Compose

export SYSTEM_USER_ID=`id --user`
export SYSTEM_USER_NAME=`id --user --name`

export WEBSERVER_PORT=${WEBSERVER_PORT:-8101}
export WEBMAILER_PORT=${WEBMAILER_PORT:-8025}

# AdServer ====================================================

export MAIL_HOST=${MAIL_HOST:-mailer}
export MAIL_PORT=${MAIL_PORT:-1025}
export MAIL_USERNAME=${MAIL_USERNAME}
export MAIL_PASSWORD=${MAIL_PASSWORD}
export MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-null}
export MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-dev@adshares.net}
export MAIL_FROM_NAME=${MAIL_FROM_NAME:-'[dev] AdShares'}

export ADSERVER_URL=${ADSERVER_URL:-http://localhost:8101}

export ADPANEL_URL=${ADPANEL_URL:-http://localhost:8102}

export ADSHARES_ADDRESS=${ADSHARES_ADDRESS:-0001-00000001-8B4E}
export ADSHARES_NODE_HOST=${ADSHARES_NODE_HOST:-esc.dock}
export ADSHARES_NODE_PORT=${ADSHARES_NODE_PORT:-9081}
export ADSHARES_SECRET=${ADSHARES_SECRET:-secret}

export APP_ENV=${APP_ENV:-local}
export APP_DEBUG=${APP_DEBUG:-true}

# =============================================================

[ -f .env ] || envsubst < .env.dist | tee .env

if [ ${OPT_BUILD} -eq 1 ]
then
    docker-compose run --rm worker composer install
    if [ ${OPT_FORCE} -eq 1 ]
    then
        docker-compose run --rm worker php artisan key:generate
    fi

    docker-compose run --rm worker php artisan package:discover
    docker-compose run --rm worker php artisan browsercap:updater

    docker-compose run --rm worker npm install
    docker-compose run --rm worker npm run dev
fi

chmod a+w -R storage

if [ ${OPT_RUN} -eq 1 ]
then
    docker-compose up --detach
fi

if [ ${OPT_MIGRATE} -eq 1 ]
then
    if [ ${OPT_MIGRATE_FRESH} -eq 1 ]
    then
        echo " > Recreate database"
        if [ ${OPT_RUN} -eq 1 ]
        then
            docker-compose exec -T worker ./artisan migrate:fresh
        else
            docker-compose run --rm worker ./artisan migrate:fresh
        fi
    else
        echo " > Update database"
        if [ ${OPT_RUN} -eq 1 ]
        then
            docker-compose exec -T worker ./artisan migrate
        else
            docker-compose run --rm worker ./artisan migrate:
        fi
    fi
fi

if [ ${OPT_LOGS} -eq 1 ]
then
    if [ ${OPT_LOGS_FOLLOW} -eq 1 ]
    then
        docker-compose logs -f
    else
        docker-compose logs
    fi
fi

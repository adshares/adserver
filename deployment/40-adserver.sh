#!/usr/bin/env bash

set -ex

PROJECT_NAME=adserver

INSTALLATION_DIR=${INSTALLATION_DIR:-/opt/adshares}

GIT_BRANCH_NAME=${GIT_BRANCH_NAME:-master}
GIT_REPO_BASE_URL=${GIT_REPO_BASE_URL:-https://github.com/adshares}

cd ${INSTALLATION_DIR}

git clone --depth=1 --single-branch --branch ${GIT_BRANCH_NAME} ${GIT_REPO_BASE_URL}/${PROJECT_NAME}.git \
    || ( git fetch && git reset --hard && git checkout ${GIT_BRANCH_NAME} )

cd ${INSTALLATION_DIR}/${PROJECT_NAME}

mkdir -pm 777 storage
mkdir -pm 777 storage/app/public/banners

export APP_NAME=AdServer
export APP_ENV=production
export APP_DEBUG=false
export APP_URL=http://localhost:8101 # publicly visible AdServer URL
export APP_KEY=base64:`date | sha256sum | head -c 32 | base64`

export LOG_CHANNEL=single

export DB_HOST=localhost
export DB_PORT=3306
export DB_DATABASE=${PROJECT_NAME}
export DB_USERNAME=${PROJECT_NAME}
export DB_PASSWORD=${PROJECT_NAME}

export BROADCAST_DRIVER=log
export CACHE_DRIVER=file
export SESSION_DRIVER=file

export SESSION_LIFETIME=120

export QUEUE_DRIVER=database

export MAIL_DRIVER=smtp # for testing purposes 'log` can be used
export MAIL_HOST=mailer
export MAIL_PORT=1025
export MAIL_USERNAME=1025
export MAIL_PASSWORD=
export MAIL_ENCRYPTION=null
export MAIL_FROM_ADDRESS=dev@adshares.net
export MAIL_FROM_NAME="[dev] AdShares"

export ADSERVER_SECRET=5LM0pJKnAlXDwSwSSqyJt
export ADSERVER_ID=AdShrek
export ADSERVER_HOST=http://localhost:8101
export ADSERVER_BANNER_HOST=http://localhost:8101

export ADSHARES_ADDRESS=0000-00000000-XXXX # account number (hot wallet) to be used by the server
export ADSHARES_NODE_HOST=t01.e11.click # account's node hostname
export ADSHARES_NODE_PORT=6511
export ADSHARES_SECRET= # account's secret key
export ADSHARES_COMMAND=`which ads`
export ADSHARES_WORKINGDIR=/tmp/adshares/ads-cache

export ADUSER_EXTERNAL_LOCATION=http://localhost:8010 # publicly visible AdServer URL
export ADUSER_INTERNAL_LOCATION=http://localhost:8010 # locally visible AdServer URL

export ADSELECT_ENDPOINT=http://localhost:8011 # locally visible AdSelect URL

export ADPAY_ENDPOINT=http://localhost:8012 # locally visible AdPay URL

export ADPANEL_URL=http://localhost # publicly visible AdPanel URL

composer install

yarn install
yarn run prod

function artisanCommand {
    ./artisan --no-interaction $@
}

artisanCommand config:cache
artisanCommand storage:link

mysql=( sudo mysql --user=root )

if [[ "$DB_DATABASE" ]]
then
    echo "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` ;" | "${mysql[@]}"
    mysql+=( "$DB_DATABASE" )
fi

if [[ "$DB_USERNAME" && "$DB_PASSWORD" ]]
then
    echo "CREATE USER '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD' ;" | "${mysql[@]}"

    if [[ "$DB_DATABASE" ]]
    then
        echo "GRANT ALL ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'%' ;" | "${mysql[@]}"
    fi

    echo 'FLUSH PRIVILEGES ;' | "${mysql[@]}"
fi

artisanCommand migrate:fresh --force --seed

artisanCommand ops:targeting-options:update
artisanCommand ops:filtering-options:update
artisanCommand ads:fetch-hosts

{
echo "0    */12 * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ads-broadcast-host.sh &> /dev/null"
echo "30   */6  * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ads-fetch-hosts.sh    &> /dev/null"
echo "*    *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ops-inventory.sh      &> /dev/null"
echo "*    *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/adpay-export.sh       &> /dev/null"
echo "59   *    * * * $INSTALLATION_DIR/${PROJECT_NAME}/artisan ops:demand:payments:block            &> /dev/null"
echo "15   *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ops-payments.sh       &> /dev/null"
echo "*/8  *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ads-scanner.sh        &> /dev/null"
echo "*/30 *    * * * cd $INSTALLATION_DIR/${PROJECT_NAME} && bash docker/cron/ops-wallet.sh         &> /dev/null"
} | tee crontab.txt

crontab crontab.txt

screen -S ${PROJECT_NAME}_worker -dm bash -c "./artisan queue:work --queue=ads,default"
screen -S ${PROJECT_NAME} -dm bash -c "php -S localhost:8101 public/index.php"


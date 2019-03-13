#!/usr/bin/env bash
set -e

HERE=$(dirname $(readlink -f "$0"))
source ${HERE}/_functions.sh root

export DEBIAN_FRONTEND=noninteractive

apt-get --yes install software-properties-common git curl gettext-base unzip supervisor vim htop screen tree

# ===

curl https://dl.yarnpkg.com/debian/pubkey.gpg -sS | apt-key add -
echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

# ===

sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 9DA31620334BD75D9DCB49F368818C72E52529D4
echo "deb [ arch=amd64 ] https://repo.mongodb.org/apt/ubuntu bionic/mongodb-org/4.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-4.0.list

# ===

add-apt-repository --yes ppa:adshares/releases

# ===

TEMP_DIR=$(mktemp --directory)

# ===

#if [[ -f /etc/os-release ]]
#then
#    source /etc/os-release
#    if [[ -z ${UBUNTU_CODENAME} ]]
#    then
#        UBUNTU_CODENAME=$(lsb_release -sc)
#    fi
#fi

#PERCONA_FILENAME="percona-release_latest.${UBUNTU_CODENAME}_all.deb"
#curl https://repo.percona.com/apt/${PERCONA_FILENAME} -sS -o ${TEMP_DIR}/${PERCONA_FILENAME}
#dpkg --install ${TEMP_DIR}/${PERCONA_FILENAME}

# ===

apt-get --yes update
apt-get --yes --no-install-recommends install \
    python python-pip python-dev gcc \
    php7.2-fpm php7.2-mysql php7.2-bcmath php7.2-bz2 php7.2-curl php7.2-gd php7.2-intl php7.2-mbstring php7.2-sqlite3 php7.2-zip php7.2-simplexml \
    ads nginx percona-server-server-5.7 nodejs yarn mongodb-org

# ===

pip install --system pipenv

# ===

COMPOSER_INSTALLER_FILENAME="composer-installer.php"
curl https://getcomposer.org/installer -sS -o ${TEMP_DIR}/${COMPOSER_INSTALLER_FILENAME}
test $(sha384sum ${TEMP_DIR}/${COMPOSER_INSTALLER_FILENAME} | head -c 96) == "48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5"
php ${TEMP_DIR}/${COMPOSER_INSTALLER_FILENAME} --install-dir=/usr/local/bin --filename=composer

# ===

rm -rf ${TEMP_DIR}

# ===

DB_USERNAME=${VENDOR_NAME}
DB_PASSWORD=${VENDOR_NAME}

mysql=( mysql --user=root )

DB_DATABASE=(adserver aduser)

if [[ "$DB_DATABASE" ]]
then
    echo "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\`;" | "${mysql[@]}"
    mysql+=( "$DB_DATABASE" )
fi

if [[ "$DB_USERNAME" && "$DB_PASSWORD" ]]
then
    echo "CREATE USER IF NOT EXISTS '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';" | "${mysql[@]}"

    if [[ "$DB_DATABASE" ]]
    then
        echo "GRANT ALL ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'%';" | "${mysql[@]}"
    fi

    echo 'FLUSH PRIVILEGES;' | "${mysql[@]}"
fi

# ===

crontab -r &> /dev/null || echo "No crontab to remove"

if [[ ${SKIP_DB_CRON_BACKUP:-1} -eq 0 ]]
then
    TEMP_FILE="$(mktemp)-crontab.txt"

    {
        echo "### Controlled by an external script - all changes will be overwritten ###"
        echo "5 5 * * * mongodump --out ${BACKUP_DIR}/mongo-\$(date -u -Iseconds)                                              &> /dev/null"
        echo "6 6 * * * mysqldump --add-drop-table --all-databases --result-file=${BACKUP_DIR}/mysql-\$(date -u -Iseconds).sql &> /dev/null"
    } | tee ${TEMP_FILE}

    crontab ${TEMP_FILE}
fi

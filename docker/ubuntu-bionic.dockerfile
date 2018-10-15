FROM ubuntu:bionic

ARG COMPOSER_HASH="93b54496392c062774670ac18b134c3b3a95e5a5e5c8f1a9f115f203b75bf9a129d5daa8ba6a13e2cc8a1da0806388a8"

ARG NODE_VERSION="10.9.0"
ARG YARN_VERSION="1.9.4"

ARG USER_NAME="dev"
ARG USER_UID="1000"
ARG USER_GID="1000"

ENV TERM xterm
ENV LOCALTIME Europe/Warsaw
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get -q update && apt-get -qyf --no-install-recommends install \
    apt-utils gpg-agent software-properties-common build-essential curl \
    && apt-get -y remove cmdtest \
    && apt-get -qy autoremove && apt-get -qy clean all \
    && rm -rf /var/lib/apt/lists/* /var/cache/apk/* /usr/share/doc/*

RUN add-apt-repository ppa:adshares/releases -y
RUN curl -sL https://deb.nodesource.com/setup_8.x | bash -
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

# update
RUN apt-get -q update && apt-get -qyf --no-install-recommends install \
        vim less dnsutils bash-completion net-tools \
        nmap mtr curl git bzip2 zip unzip tree mc wget \
        openssl openssh-client openssh-server \
        gnupg2 dirmngr connect-proxy \
        mysql-client zsh \
        php7.2-fpm php7.2-mysql php7.2-bcmath php7.2-bz2 php7.2-cli php7.2-curl php7.2-gd php7.2-intl php7.2-json php7.2-mbstring php7.2-opcache php7.2-pgsql php7.2-readline php7.2-sqlite3 php7.2-xml php7.2-xmlrpc php7.2-xsl php7.2-zip \
        php-xdebug \
        ads ads-tools \
        nodejs yarn \
    && apt-get -qy autoremove && apt-get -qy clean all \
    && rm -rf /var/lib/apt/lists/* /var/cache/apk/* /usr/share/doc/*

# sshd config
RUN mkdir /var/run/sshd
RUN sed "s|#PasswordAuthentication\s*yes|PasswordAuthentication no|g" /etc/ssh/sshd_config
RUN sed "s|session\s*required\s*pam_loginuid.so|session optional pam_loginuid.so|g" -i /etc/pam.d/sshd

# timezone
RUN ln -sf /usr/share/zoneinfo/$LOCALTIME /etc/localtime
#RUN echo "date.timezone = \"${LOCALTIME}\"" | tee --append $PHP_INI_DIR/conf.d/00-default.ini

# composer
RUN wget https://getcomposer.org/installer --quiet --output-document=/tmp/composer-setup.php \
    &&         echo "  expected: $COMPOSER_HASH" \
    && php -r "echo 'calculated: '. hash_file('SHA384','/tmp/composer-setup.php').PHP_EOL;" \
    && php -r "exit(strcmp(hash_file('SHA384','/tmp/composer-setup.php'),getenv('COMPOSER_HASH')));" \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php

# skel
COPY skel /etc/skel/
RUN chmod u+rwX,go-rwx -R /etc/skel/

# root
COPY skel /root/
RUN chmod u+rwX,go-rwx -R /root/

# group
RUN groupmod --non-unique --gid $USER_GID www-data

# user
RUN useradd \
    --uid $USER_UID \
    --no-user-group --gid $USER_GID \
    --create-home \
    --shell /bin/bash \
    $USER_NAME

# scripts
COPY bin /usr/local/bin/

ARG SYSTEM_USER_ID
ARG SYSTEM_USER_NAME

RUN if [ $SYSTEM_USER_ID -gt 1000 ];then \
    useradd \
    --uid $SYSTEM_USER_ID \
    --no-user-group \
    --create-home \
    $SYSTEM_USER_NAME \
    ;fi

# Credit: PHPDocker.io
COPY overrides.conf /etc/php/7.2/fpm/pool.d/z-overrides.conf

ENTRYPOINT ["docker-php-entrypoint.sh"]
CMD ["php-fpm.sh"]
EXPOSE 9000



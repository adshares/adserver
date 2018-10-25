#!/usr/bin/env bash

mysql --version || apt-get -qq -y install mysql-server

apt-get -qq -y install php7.2-cli php7.2-fpm php7.2-curl php7.2-mysql

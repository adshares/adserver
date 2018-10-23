#!/usr/bin/env bash

mysql --version || apt-get -y install mysql-server

apt-get install -y php7.2-cli php7.2-curl php7.2-mysql

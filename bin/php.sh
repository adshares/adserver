#!/usr/bin/env bash

exec docker-compose exec -T -u dev application php \
-dxdebug.remote_port="9001" \
-dxdebug.remote_enable="true" \
-dxdebug.remote_autostart="true" \
-dxdebug.remote_connect_back="true" \
-dxdebug.remote_mode="req" \
-dxdebug.idekey="PHPSTORM" \
-dxdebug.max_nesting_level="512" \
-dxdebug.cli_color="1" \
-dxdebug.auto_trace="1" \
-dxdebug.extended_info="1" \
"$@"

<p align="center">
    <a href="https://adshares.net/" title="Adshares sp. z o.o." target="_blank">
        <img src="https://adshares.net/logos/ads.svg" alt="Adshares" width="100" height="100">
    </a>
</p>
<h3 align="center"><small>Adshares - AdServer</small></h3>
<p align="center">
    <a href="https://github.com/adshares/adserver/issues/new?template=bug_report.md&labels=Bug">Report bug</a>
    ·
    <a href="https://github.com/adshares/adserver/issues/new?template=feature_request.md&labels=New%20Feature">Request feature</a>
    ·
    <a href="https://github.com/adshares/adserver/wiki">Wiki</a>
</p>
<p align="center">
    <a href="https://travis-ci.org/adshares/adserver" title="master" target="_blank">
        <img src="https://travis-ci.org/adshares/adserver.svg?branch=master" alt="Build Status">
    </a>
</p>

## Quick Start

Run: 

```bash
cp --no-clobber docker-compose.override.yaml.dist docker-compose.override.yaml
[ -f .env ] || SYSTEM_USER_ID=`id --user` SYSTEM_USER_NAME=`id --user --name` envsubst \${SYSTEM_USER_ID},\${SYSTEM_USER_NAME} < .env.dist | tee .env
docker-compose config # just to check the config
docker-compose run --rm dev composer install
docker-compose run --rm dev composer dump-autoload
docker-compose up --detach
docker-compose exec dev ./artisan migrate
docker-compose exec dev ./artisan package:discover
docker-compose exec dev ./artisan browsercap:updater
docker-compose exec dev npm install
docker-compose exec dev npm run dev # if you want dynamic updates change this line to 'npm run watch' or 'npm run watch-poll' if issues in watchin ;-)
```

Go to:
- [http://localhost:8101/](http://localhost:8101/) for the server
- [http://localhost:8025/](http://localhost:8025/) for an e-mail interceptor  

## Documentation

- [Wiki](https://github.com/adshares/adserver/wiki)
- [Changelog](CHANGELOG.md)

## Contributing

- Please follow our [Contributing Guidelines](docs/CONTRIBUTING.md)

## Versioning

- We use [Semantic Versioning](http://semver.org/).
- See available [versions](https://github.com/adshares/adserver/tags). 

## Authors

- **[Tomek Grzechowski](https://github.com/yodahack)**
- **[Maciej Pilarczyk](https://github.com/m-pilarczyk)**
- **[Paweł Zaremba](https://github.com/pawzar)**

...and other [contributors](https://github.com/adshares/adserver/contributors).

## Related projects

- [AdPanel](https://github.com/adshares/adpanel)
- [PHP ADS Client](https://github.com/adshares/adserver-php-client)

## License

This work is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This work is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
[GNU General Public License](LICENSE) for more details.

You should have received a copy of the License along with this work.
If not, see <https://www.gnu.org/licenses/gpl.html>.


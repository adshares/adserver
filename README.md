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

```bash
bin/init.sh --build --start --migrate
```

Usage:
```
bin/init.sh         Initialize environment
  --clean           Remove containers
                     + remove dependencied and environment files when used with --force
  --build           Download dependencies
                     + regenerate secret when used with --force
  --start           Start docker containers
  --migrate         Update database schema (creating it if neccesary)
                      and regenerate secret when used with --force
  --migrate-fresh   Remove database before migration 
  --logs            Show logs after everything else is done
  --logs-follow     ...and follow them
  --stop            Stop all containers (overrides all other options above)
``` 

With the default you should have two working locations:
- [http://localhost:8101/](http://localhost:8101/) for the server
- [http://localhost:8025/](http://localhost:8025/) for an e-mail interceptor  

## Documentation

- [Wiki](https://github.com/adshares/adserver/wiki)
- [Changelog](CHANGELOG.md)
- [Authors](https://github.com/adshares/adserver/contributors)

## Contributing

- Please follow our [Contributing Guidelines](docs/CONTRIBUTING.md)

## Versioning

- We use [Semantic Versioning](http://semver.org/).
- See available [versions](https://github.com/adshares/adserver/tags). 

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

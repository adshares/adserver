<p align="center">
    <a href="https://adshares.net/" title="Adshares sp. z o.o." target="_blank">
        <img src="https://adshares.net/logos/ads.svg" alt="Adshares" width="100" height="100">
    </a>
</p>
<h3 align="center"><small>Adshares / AdServer</small></h3>
<p align="center">
    <a href="https://github.com/adshares/adserver/issues/new?template=bug_report.md&labels=Bug">Report bug</a>
    ·
    <a href="https://github.com/adshares/adserver/issues/new?template=feature_request.md&labels=New%20Feature">Request feature</a>
    ·
    <a href="https://github.com/adshares/adserver/wiki">Wiki</a>
</p>
<p align="center">
    <a href="https://travis-ci.org/adshares/adserver" title="Build Status" target="_blank">
        <img src="https://travis-ci.org/adshares/adserver.svg?branch=master" alt="Build Status">
    </a>
    <a href="https://sonarcloud.io/dashboard?id=adshares-adserver" title="Code Quality" target="_blank">
        <img src="https://sonarcloud.io/api/project_badges/measure?project=adshares-adserver&metric=alert_status" alt="Code Quality">
    </a>
</p>

AdServer is the core software behind the ecosystem.

## Quick Start (on Ubuntu 18.04)

Get source code
```bash
git clone https://github.com/adshares/adserver.git
```

Install dependencies
```bash
sudo adserver/deployment/bootstrap.sh `id --user --name`
```

Install services (NO `sudo`)
```bash
# This will loop through all services
adserver/deployment/bootstrap.sh
```

## Documentation

- [Wiki](https://github.com/adshares/adserver/wiki)
- [Changelog](CHANGELOG.md)
- [Contributing Guidelines](docs/CONTRIBUTING.md)
- [Authors](https://github.com/adshares/adserver/contributors)
- Available [Versions](https://github.com/adshares/adserver/tags) (we use [Semantic Versioning](http://semver.org/))

## Related projects

- [AdUser](https://github.com/adshares/aduser)
- [AdSelect](https://github.com/adshares/adselect)
- [AdPay](https://github.com/adshares/adpay)
- [AdPanel](https://github.com/adshares/adpanel)
- [ADS](https://github.com/adshares/ads)

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

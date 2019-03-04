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

## Prerequisites
 - Existing ADS account (with credentials)
 - A clean Ubuntu/Bionic install
 - You are logged in as a user with `sudo` privileges
 - You have mail server ready somewhere (use something like [MailHog](https://github.com/mailhog/MailHog) for local testing)
 
## Quick Start (on Ubuntu 18.04)

### Get source code
```bash
git clone https://github.com/adshares/adserver.git
```
### Prepare environment
Install all required software and copy all the below-mentioned scripts and configs to `/opt/adshares/.deployment-scripts`.
```bash
sudo adserver/deployment/bootstrap.sh
```
> The script above creates such a separate `adshares` user (without sudo privileges) to be the owner of all the installed services.

# Configure services
```bash
sudo --login --user adshares /opt/adshares/.deployment-scripts/configure.sh
```

Script will ask you to provide your ADS wallet credentials, so please create ADS account first.

### Install and start services

Note that there are many environment variables you can override to tweak the behavior of the services.
Every project has the `.env` file where you can find most of configuration options. 

```bash
sudo /opt/adshares/.deployment-scripts/install.sh
```

You can now access you adserver frontend through your browser on the configured domain and port.

> If you installed all the stuff locally just point your browser to http://localhost.
> - AdUser: `8010`
> - AdSelect: `8011`
> - AdPay: `8012`

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

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
 - A clean Ubuntu/Bionic install
 - You are logged in as a user with `sudo` privileges
 - You have mail server ready somewhere (use something like [MailHog](https://github.com/mailhog/MailHog) for local testing)
 
## Quick Start (on Ubuntu 18.04)

### Get source code
```bash
git clone https://github.com/adshares/adserver.git
```
### Prepare environment
We recommend creating a separate user (without sudo privileges) to be the owner of all the installed services.
> The script below creates such a user (called `adshares` by default).

```bash
sudo adserver/deployment/bootstrap.sh
```
> Should you wish to create a user with a different name, add an argument to the above command specifying it. 
> Use ``` `id --user --name` ``` as the argument to pass the current user's name.

The script will install all required software and will copy all the below-mentioned scripts and configs to `/opt/adshares/.deployment-scripts` (setting ownership to `adshares` user).

The one-liners are created with `sudo` to run the scripts as the `adshares` user. You can omit `sudo --login --user adshares` if you want to run them as the current user.

Note that there are many environment variables you can override to tweak the behavior of the scripts (just inspect the scripts and configs to see what is being used).
The variables are set to reasonable defaults. Adjust them to your needs :)

# Install helper services
```bash
sudo --login --user adshares /opt/adshares/.deployment-scripts/10-aduser.sh
sudo --login --user adshares INSTALL_BROWSCAP_DATA=1 /opt/adshares/.deployment-scripts/11-aduser_browscap.sh
sudo --login --user adshares INSTALL_GEOLITE_DATA=1 /opt/adshares/.deployment-scripts/12-aduser_geolite.sh

sudo --login --user adshares /opt/adshares/.deployment-scripts/20-adselect.sh
sudo --login --user adshares /opt/adshares/.deployment-scripts/30-adpay.sh
```
Copy configs and start standalone services
```bash
sudo cp -rf /opt/adshares/.deployment-scripts/supervisor/conf.d/aduser*.conf /etc/supervisor/conf.d
sudo cp -rf /opt/adshares/.deployment-scripts/supervisor/conf.d/adselect*.conf /etc/supervisor/conf.d
sudo cp -rf /opt/adshares/.deployment-scripts/supervisor/conf.d/adpay*.conf /etc/supervisor/conf.d
sudo service supervisor restart
```
The services are now listening to requests on `800x` ports on `localhost`:
- AdUser: `8010`
- AdSelect: `8011`
- AdPay: `8012`

Install AdServer (with workers)
```bash
sudo --login --user adshares DB_MIGRATE=1 DB_SEED=1 /opt/adshares/.deployment-scripts/40-adserver.sh
sudo --login --user adshares /opt/adshares/.deployment-scripts/41-adserver_worker.sh

sudo cp -rf /opt/adshares/.deployment-scripts/supervisor/conf.d/adserver*.conf /etc/supervisor/conf.d
sudo service supervisor restart
```
Build static version of AdPanel
```bash
sudo --login --user adshares /opt/adshares/.deployment-scripts/50-adpanel.sh
```

### Start public web services

Reconfigure Nginx
```bash
sudo cp -rf /opt/adshares/.deployment-scripts/nginx/conf.d/*.conf /etc/nginx/conf.d
sudo service nginx reload
```

Publicly accessible services now listen on all interfaces on three new ports:
- AdServer - the API server on port `8001` 
- AdPanel - the system's user interface on port `8002`
- AdUser - the visible part of the tracking service on port `8003`

> If you installed all the stuff locally just point your browser to http://localhost:8002.

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

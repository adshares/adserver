# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Currency handling during payments processing

## [0.6.2] - 2019-04-19
### Fixed
- Inventory import new banners - could not map to demand id which not exist

## [0.6.1] - 2019-04-18
### Improved
- Keyword mapping for user data

## [0.6.0] - 2019-04-16
### Added
- Bonus credits for new users (on email confirmation)

## [0.5.4] - 2019-04-12
### Fixed
- Advertiser charts for campaign details show detailed data

## [0.5.3] - 2019-04-12
### Added
- Bonus credits for advertising expenses
### Improved
- Campaign Reports
### Fixed
- Ensuring sufficient funds for future expenses 

## [0.5.2] - 2019-04-11
### Fixed
- Cron jobs output
### Changed
- Headers sent to AdUser

## [0.5.1] - 2019-04-10
### Fixed
- Update migration

## [0.5.0] - 2019-04-09
### Added
- Publisher & Advertiser Reports in CSV format 

### Changed
- Deleted campaigns are no longer included in stats
 
## [0.4.0] - 2019-04-01
### Added 
- Administrator panel endpoints: settings, regulations, license
- Administrator account creation
- License handling
- Banner classification filters

### Changed
- AdUser integration
- Build scripts
- Handle ZIP files instead of HTML files
- Network host updating: omit not active
- Inventory export/import: accept active campaigns only
- Hot wallet feature is turned off by default

## [0.3.2] - 2019-03-08
### Changed
- Inventory import - process all network hosts

## [0.3.1] - 2019-03-08
### Added 
- Backward compatibility for Service Discovery 

## [0.3.0] - 2019-03-08
### Added
- Banner Classification

### Changed
- Server info data format for Service Discovery 

## [0.2.1]

## [0.2.0]

## [0.1.0]

[Unreleased]: https://github.com/adshares/adserver/compare/v0.6.2...develop
[0.6.2]: https://github.com/adshares/adserver/compare/v0.6.1...v0.6.2
[0.6.1]: https://github.com/adshares/adserver/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/adshares/adserver/compare/v0.5.4...v0.6.0
[0.5.4]: https://github.com/adshares/adserver/compare/v0.5.3...v0.5.4
[0.5.3]: https://github.com/adshares/adserver/compare/v0.5.2...v0.5.3
[0.5.2]: https://github.com/adshares/adserver/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/adshares/adserver/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/adshares/adserver/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/adshares/adserver/compare/v0.3.2...v0.4.0
[0.3.2]: https://github.com/adshares/adserver/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/adshares/adserver/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/adshares/adserver/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/adshares/adserver/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/adshares/adserver/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/adshares/adserver/compare/8ebb8fc381267dec45126342f52c2e18bf9946aa...v0.1.0

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.10.6] - 2021-09-22
### Added
- Quality taxonomy support
### Changed
- Including filtering in classification
### Fixed
- Admin report for inactive campaigns

## [1.10.5] - 2021-09-02
### Added
- Campaign cloning

## [1.10.4] - 2021-09-01
### Added
- Fallback script rate option
### Fixed
- Minor fixes

## [1.10.3] - 2021-08-26
### Added
- Default targeting & filtering
### Fixed
- Minor fixes

## [1.10.2] - 2021-08-25
### Added
- Serve subdomain for Aduser events
- Campaign name in admin reports
### Fixed
- Quality tag in filtering
- Checking site domain

## [1.10.1] - 2021-08-24
### Added
- Hourly interval admin report
- Quality tags support
### Fixed
- Minor fixes

## [1.10.0] - 2021-08-20
### Added
- Invoices & deposit in FIAT
### Fixed
- Minor fixes

## [1.9.2] - 2021-08-13
### Added
- Aduser info in admin report

## [1.9.1] - 2021-08-12
### Fixed
- Minor fixes

## [1.9.0] - 2021-08-05
### Added
- Refund referral program
- Restricted & private registration modes
- Manual account confirmation

## [1.8.2] - 2021-07-16
### Changed
- Multi-threaded event sending to Adpay

## [1.8.1] - 2021-06-21
### Changed
- Caching network data

## [1.8.0] - 2021-06-21
### Added
- CDN support
- Integration with SIA Skynet

## [1.7.2] - 2021-06-16
### Fixed
- Malformed event data

## [1.7.1] - 2021-06-15
### Added
- Extend outdated campaign
### Changed
- Remove an unnecessary data from the context
- Upgrade to PHP 7.4
- Upgrade to Composer 2

## [1.7.0] - 2021-06-01
### Added
- Bid strategies
- Storing sites' information (page rank) in database
- Setting panel placeholders with an e-mail notification
- Rejecting site's domains
- Send an email notification once the banner was classified
- Reassessment of distinctive sites
- Sites' categories
- Server's statistics backup
### Changed
- Do not allow site's domain starts with a dot
- Taxonomy processing, allow multiple levels
### Fixed
- Retry ads transaction after error while processing demand payments
- Adding campaign with conversions
### Removed
- AdUser url from find.js

## [1.6.3] - 2020-03-03
### Added
- Publishers stats

## [1.6.2] - 2020-02-27
### Added
- Reports stored on the server
### Changed
- Counting set bits in binary string
### Fixed
- Publishers' domains in targeting reach

## [1.6.1] - 2020-02-20
### Fixed
- Response when campaign/site total statistics are empty 

## [1.6.0] - 2020-02-19
### Added
- Targeting Reach: computing, exchange between adservers
- BTC withdraw
### Fixed
- Conversion redirect response
- Reports optimization

## [1.5.5] - 2020-02-05
### Fixed
- NowPayments no fee
- Keys in campaign keywords

## [1.5.4] - 2020-01-30
### Added
- Classifications in campaigns endpoint
### Changed
- Indexes in migrations for payments and network_case_payments tables
### Fixed
- Advertisers' live stats contain last 10 minutes

## [1.5.3] - 2020-01-27
### Added
- Minimal budget settings
- Conversions' statistics endpoint
- Site's codes endpoint

## [1.5.2] - 2020-01-17
### Added
- Search by domain in admin panel
### Changed
- Advertisers' and Publishers' statistics are updated live
### Fixed
- Add fee to ADS exchange request

## [1.5.1] - 2020-01-16
### Changed
- Move NowPayments info from check to depositInfo
### Fixed
- Https in NowPayments callback
- Global statistics

## [1.5.0] - 2020-01-15
### Added
- NowPayments integration
- Edit conversions
### Changed
- CPA only page rank support
- Empty direct banner content to campaign landing URL
- Remove filtering option "Content Type"
- Queries for statistics data (charts/tables) to increase performance
### Fixed
- Deleting always at least one row during events clear command (ops:events:clear)

## [1.4.9] - 2020-01-07
### Fixed
- User context constructor usage

## [1.4.8] - 2020-01-03
### Added
- Edit content in direct ads
### Changed
- Content field name in direct ads

## [1.4.7] - 2019-12-11
### Changed
- Register URL extension
- Do not require TLS/SSL on publisher site
- Show CPA banners for unknown sites

## [1.4.6] - 2019-12-10
### Changed
- Update advertiser statistics for hours affected by conversions
- Export payments for cases valued zero

## [1.4.5] - 2019-12-05
### Changed
- Update statistics with conversions

## [1.4.4] - 2019-12-05
### Added
- Global postback
### Fixed
- Zero transfers between adservers
### Removed
- Conversion budget

## [1.4.3] - 2019-11-26
### Changed
- PHPUnit tests use MySQL database
### Fixed
- Invalid size in internal classifier

## [1.4.3] - 2019-11-26
### Changed
- PHPUnit tests use MySQL database
### Fixed
- Invalid size in internal classifier

## [1.4.2] - 2019-11-22
### Fixed
- Incorrect find.js context
- Adserver doubled statistic
- Add keywords sanitizing

## [1.4.1] - 2019-11-22
### Fixed
- Exception when site or user is missing during finding banners

## [1.4.0] - 2019-11-22
### Added
- Pops ad units support
- Site domain support
### Changed
- Transform width and height into size 
- Default page rank to 0

## [1.3.3] - 2019-11-15
### Added
- Landing URL placeholders
### Changed
- Export events to AdSelect
### Fixed
- Statistics of unique visits

## [1.3.2] - 2019-11-12
### Added
- Page rank handling
- Processing payment reports selected by id
### Changed
- Export to adselect
- Processing event payments on supply-side
- Computing statistics of publishers
### Fixed
- Conversion deletion during campaign change
- Processing payment reports while events were not exported
- Payment details for missing payments

## [1.3.1] - 2019-10-30
### Changed
- Merge command ads:get-tx-in and ads:process-tx
- PHP-FPM socket name

## [1.3.0] - 2019-10-29
### Added
- Banner checksum fragment to serve url
- Exporting conversions to adpay
### Changed
- Sum incomes/expenditures from different adservers in billing history
- Getting payment report from adpay (optimization)
### Fixed
- Grouping events by day in billing history
- Importing banners when external classifier is not defined
### Removed
- NetworkEventLog and related classes

## [1.2.5] - 2019-10-01
### Added
- Exporting conversions' definition to adpay
- Supports createjs and non-ascii names in HTML banners
### Fixed
- Invalid classification after banner hash change

## [1.2.4] - 2019-09-30
### Added
- Admin wallet info
- Clearing old events
### Fixed
- Nginx deploy (additional hosts)

## [1.2.3] - 2019-09-24
### Changed
- Add created column to network case payments
### Fixed
- Missing 'Access-Control-Allow-Origin' header during banner find
- Script and css escaping

## [1.2.2] - 2019-09-23
### Changed
- Split network event logs table

## [1.2.1] - 2019-09-20
### Changed
- Internal classifier rejects banners only
- Require https

## [1.2.0] - 2019-09-11
### Added
- Cancel expired withdrawal automatically
- Billing history filtering
### Changed
- Import inventory: reject unclassified banners

## [1.1.2] - 2019-08-30
### Fixed
- Check event context before save
- Handle empty data

## [1.1.1] - 2019-08-26
### Added
- Conversion - pack one
- Sending email from a file

## [1.1.0] - 2019-08-22
### Added
- Use of an external classifier
- Inform user by an email when campaigns are suspended or resumed
### Changed
- Use single lock for supply inventory commands
- Import inventory: update using source address
### Fixed
- Deleting campaigns during inventory import

## [0.12.0] - 2019-06-25
### Changed
- Join supply payments commands
- Update crontab generation
- Retry withdraw when 'Lock user failed' exception occurs
- Paginate payment report
- Export events to AdSelect max from 7 days 

### Added
- Dump events older than 32 days
- Send tracking_id field to AdSelect  
- Send campaign's budget information to AdSelect

## [0.11.0] - 2019-06-12
### Changed
- AdSelect internal client to reflect changes in [v0.2.0](https://github.com/adshares/adselect/releases/tag/v0.2)

## [0.10.0] - 2019-06-04
### Added
- Comparing campaign targeting with the schema
- User referrer
- ADS address, statistics and operator fees to server information (info.json)

## [0.9.0] - 2019-05-30
### Added
- Event statistics reports for operators
### Improved
- Bonus spending scheme
### Fixed
- Reports for specific campaign/site should not have information about other campaigns/sites

## [0.8.1] - 2019-05-21
### Improved
- Event indexing

## [0.8.0] - 2019-05-20
### Added
- Statistics aggregation hourly
### Fixed
- Middleware ordering for impersonation

## [0.7.3] - 2019-05-13
### Improved
- Impersonation

## [0.7.2] - 2019-05-10
### Improved
- True Excel format reports

## [0.7.1] - 2019-05-09
### Added
- User impersonation by server admin
- Recognize fake events in statistics
- Columns to statistics report: all views/clicks, invalid views/clicks rate, unique views
### Changed
- Formulas for computing CPC, CPM, RPC, RPM. Total cost is used instead of payment for event of particular type
### Fixed
- Users' ad expenses are in ADS not in currency
### Improved
- AdPay event exporting

## [0.7.0] - 2019-04-29
### Added
- Currency handling during payments processing
- Export events (paid and unpaid) in packages

## [0.6.7] - 2019-04-26
### Added
- Classification banner filtering by landingUrl

## [0.6.6] - 2019-04-25
### Fixed
- IP column size
- Build process
- Invalid UUID error on Event export 

## [0.6.5] - 2019-04-25
### Fixed
- Campaign keywords for AdSelect

## [0.6.4] - 2019-04-24
### Added
- Command execution lock
### Improved
- Inventory export (keyword mapping)

## [0.6.3] - 2019-04-23
### Fixed
- Event export

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

[Unreleased]: https://github.com/adshares/adserver/compare/v1.10.6...develop
[1.10.6]: https://github.com/adshares/adserver/compare/v1.10.5...v1.10.6
[1.10.5]: https://github.com/adshares/adserver/compare/v1.10.4...v1.10.5
[1.10.4]: https://github.com/adshares/adserver/compare/v1.10.3...v1.10.4
[1.10.3]: https://github.com/adshares/adserver/compare/v1.10.2...v1.10.3
[1.10.2]: https://github.com/adshares/adserver/compare/v1.10.1...v1.10.2
[1.10.1]: https://github.com/adshares/adserver/compare/v1.10.0...v1.10.1
[1.10.0]: https://github.com/adshares/adserver/compare/v1.9.2...v1.10.0
[1.9.2]: https://github.com/adshares/adserver/compare/v1.9.1...v1.9.2
[1.9.1]: https://github.com/adshares/adserver/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/adshares/adserver/compare/v1.8.2...v1.9.0
[1.8.2]: https://github.com/adshares/adserver/compare/v1.8.1...v1.8.2
[1.8.1]: https://github.com/adshares/adserver/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/adshares/adserver/compare/v1.7.2...v1.8.0
[1.7.2]: https://github.com/adshares/adserver/compare/v1.7.1...v1.7.2
[1.7.1]: https://github.com/adshares/adserver/compare/v1.7.0...v1.7.1
[1.7.0]: https://github.com/adshares/adserver/compare/v1.6.3...v1.7.0
[1.6.3]: https://github.com/adshares/adserver/compare/v1.6.2...v1.6.3
[1.6.2]: https://github.com/adshares/adserver/compare/v1.6.1...v1.6.2
[1.6.1]: https://github.com/adshares/adserver/compare/v1.6.0...v1.6.1
[1.6.0]: https://github.com/adshares/adserver/compare/v1.5.5...v1.6.0
[1.5.5]: https://github.com/adshares/adserver/compare/v1.5.4...v1.5.5
[1.5.4]: https://github.com/adshares/adserver/compare/v1.5.3...v1.5.4
[1.5.3]: https://github.com/adshares/adserver/compare/v1.5.2...v1.5.3
[1.5.2]: https://github.com/adshares/adserver/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/adshares/adserver/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/adshares/adserver/compare/v1.4.9...v1.5.0
[1.4.9]: https://github.com/adshares/adserver/compare/v1.4.8...v1.4.9
[1.4.8]: https://github.com/adshares/adserver/compare/v1.4.7...v1.4.8
[1.4.7]: https://github.com/adshares/adserver/compare/v1.4.6...v1.4.7
[1.4.6]: https://github.com/adshares/adserver/compare/v1.4.5...v1.4.6
[1.4.5]: https://github.com/adshares/adserver/compare/v1.4.4...v1.4.5
[1.4.4]: https://github.com/adshares/adserver/compare/v1.4.3...v1.4.4
[1.4.3]: https://github.com/adshares/adserver/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/adshares/adserver/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/adshares/adserver/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/adshares/adserver/compare/v1.3.3...v1.4.0
[1.3.3]: https://github.com/adshares/adserver/compare/v1.3.2...v1.3.3
[1.3.2]: https://github.com/adshares/adserver/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/adshares/adserver/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/adshares/adserver/compare/v1.2.5...v1.3.0
[1.2.5]: https://github.com/adshares/adserver/compare/v1.2.4...v1.2.5
[1.2.4]: https://github.com/adshares/adserver/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/adshares/adserver/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/adshares/adserver/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/adshares/adserver/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/adshares/adserver/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/adshares/adserver/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/adshares/adserver/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/adshares/adserver/compare/v0.12.0...v1.1.0
[0.12.0]: https://github.com/adshares/adserver/compare/v0.11.0...v0.12.0
[0.11.0]: https://github.com/adshares/adserver/compare/v0.10.0...v0.11.0
[0.10.0]: https://github.com/adshares/adserver/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/adshares/adserver/compare/v0.8.1...v0.9.0
[0.8.1]: https://github.com/adshares/adserver/compare/v0.8.0...v0.8.1
[0.8.0]: https://github.com/adshares/adserver/compare/v0.7.3...v0.8.0
[0.7.3]: https://github.com/adshares/adserver/compare/v0.7.2...v0.7.3
[0.7.2]: https://github.com/adshares/adserver/compare/v0.7.1...v0.7.2
[0.7.1]: https://github.com/adshares/adserver/compare/v0.7.0...v0.7.1
[0.7.0]: https://github.com/adshares/adserver/compare/v0.6.7...v0.7.0
[0.6.7]: https://github.com/adshares/adserver/compare/v0.6.6...v0.6.7
[0.6.6]: https://github.com/adshares/adserver/compare/v0.6.5...v0.6.6
[0.6.5]: https://github.com/adshares/adserver/compare/v0.6.4...v0.6.5
[0.6.4]: https://github.com/adshares/adserver/compare/v0.6.3...v0.6.4
[0.6.3]: https://github.com/adshares/adserver/compare/v0.6.2...v0.6.3
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

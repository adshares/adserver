
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.5.0] - 2023-05-15
### Added
- Persist turnover data
- Turnover monitoring API
- Validate ads.txt
- Custom favicon
### Changed
- Payment processing loop is continued if exchange rate errored
- Reject site during find if it has rejected domain
- Check user ID during event export
### Fixed
- Do not process payments older than 2 days (by default)
- Start date for quarterly statistics

## [2.4.13] - 2023-04-21
### Changed
- Payment processing loop is continued if exchange rate for particular period is not available
- Disallow robots to index pages
### Fixed
- Do not process payments older than 2 days (by default)

## [2.4.12] - 2023-03-31
### Added
- Non-withdrawable user ledger entries
- Optional parameter topframe in find request
### Fixed
- Do not compute targeting reach for adserver without supply capabilities
- Backward compatibility for HTML code from demand on view event

## [2.4.11] - 2023-03-15
### Changed
- Increase user activity interval
- Extract sites' reject reason to separate table (normalization)
### Fixed
- Return CORS headers during maintenance

## [2.4.10] - 2023-03-08
### Added
- Impression's account passed to demand
### Fixed
- Unified impression ID format on supply side

## [2.4.9] - 2023-02-28
### Fixed
- No credentials while logging view

## [2.4.8] - 2023-02-27
### Changed
- Add `noindex` header

## [2.4.7] - 2023-02-27
### Added
- Locking user's account after several failed login attempts
- Find response URLs extended with impression ID and placement ID
### Changed
- Demand view endpoint returns JSON or HTML based on header
### Fixed
- Presenting maintenance mode in info.json
- Do not accept requests in maintenance mode
- UUIDs are allowed for Page "Why" and reporting ads

## [2.4.6] - 2023-02-20
### Fixed
- NowPayments order ID initialization

## [2.4.5] - 2023-02-20
### Fixed
- Creating generic Metaverse campaign

## [2.4.4] - 2023-02-17
### Added
- Polka City URL validation
### Changed
- Do not hash impression ID when passed as UUID
- Allow zone ID instead on full context while logging event on supply side

## [2.4.3] - 2023-02-15
### Fixed
- Missing creativeId during find

## [2.4.2] - 2023-02-14
### Changed
- Page "Why" links to landing page
- Default landing page URL

## [2.4.1] - 2023-02-13
### Fixed
- Do not accept invalid metaverse URLs and domains
### Added
- Landing URL to info.json
- Users' campaign media endpoint
### Changed
- Favicons and watermark compliant with brand book

## [2.4.0] - 2023-02-09
### Added
- Filtering campaigns by medium and vendor
- Fetching community fee from external service
### Changed
- Campaign can target any vendor of medium
- Extract rejected domains from configuration API
- Page "Why" appearance 
### Fixed
- Dynamic find with empty types or mimes
- Banner classification list (correct medium)

## [2.3.2] - 2023-01-31
### Fixed
- Calculating waiting payments

## [2.3.1] - 2023-01-31
### Fixed
- Acceptance for active sites
- Account confirmation in case of registration during find
- Filtering by non-empty string which casts to false, e.g. '0'

## [2.3.0] - 2023-01-26
### Added
- Server event type list to monitoring API
- Command to upsert configuration
- Option to require site approval for specific medium
- Option to set site's filtering during automatic creation
- Reject matching sites on rejecting domains
- Possibility to grant user administrator privileges
- Extend user data with roles
### Changed
- Authorize targeting reach fetches
- Do not allow to update active campaign without creatives
- Use UUID as ID in campaign APIv2
- Store uploaded files (creatives) in database
- Extend validation during file upload
- Do not duplicate code for Decentraland find
- Synchronize unreachable hosts
- Support new adpay response
### Removed
- Redundant pagination data
### Fixed
- Allow creating campaign draft without creatives
- Reading video dimensions with ffmpeg
- Banner's content lazy loading
- Forbid site duplicates
- Clear not whitelisted hosts
- Statistics computation in case of same user visiting different domains
- User which receives administrator or moderator privileges can impersonate
- Do not return code for sites that have not been positively verified

## [2.2.7] - 2023-01-19
### Fixed
- Exporting events to AdPay (overwrite invalid page url)

## [2.2.6] - 2023-01-10
### Fixed
- Exporting events to AdPay (check response)
- Saving event's domain while fetching context

## [2.2.5] - 2023-01-05
### Fixed
- Exporting campaign's targeting to AdSelect

## [2.2.4] - 2022-12-29
### Fixed
- Importing inventory while AdServer does not return JSON

## [2.2.3] - 2022-12-19
### Added
- Zone scopes
## Changed
- Metaverse zones consolidation
- Send medium and vendor to AdPay
- Campaign amounts in USD (APIv2)
- Legacy user actions which are handled by monitoring API
### Fixed
- Missing meta in event pagination

## [2.2.2] - 2022-12-12
### Added
- Banner rotate
### Changed
- Found banner must match campaign medium 

## [2.2.1] - 2022-12-08
### Fixed
- Auth redirect chain
- Validation messages when creating campaign
- Only found banner are returned (with request ID)

## [2.2.0] - 2022-12-07
### Added
- Deleting referral links
- Referral links pagination
- Server events log
- Logging changes in users table
- OAuth2
- Campaigns APIv2
- Dynamic find
### Changed
- Network host which does not broadcast is marked as failed before deleting
- Do not request banners when zones are invalid
- Configuration and monitoring API in camel case
- Find script uses HTTP POST method
- Placement code attribute to data-placement
- Increased authentication security
### Removed
- Legacy campaign classification columns
### Fixed
- Changing network host state after incoming broadcast
- Return default AdPanel's title if not set
- Do not transform campaign's targeting
- Campaign validation when files are missing
- Direct banner validation (URL not required)
- Configuration key case

## [2.1.1] - 2022-10-04
### Added
- Monitoring network hosts
- Reset network host's connection error
### Changed
- Extended stored network hosts' data
### Fixed
- Missing zones in find request
- Do not resend email activation while email is not set
- Remove tracking context cache

## [2.1.0] - 2022-09-28
### Added
- Possibility to set application currency other than ADS
- AdPanel's placeholders to configuration API
- Allow change user roles
- Login info to panel placeholders
### Changed
- Split user access for advertisers and publishers
- Configuration API returns data after store
### Removed
- Registration forms URLs from info
### Fixed
- Do not expose SMTP password in configuration API
- Remove deprecated zip* functions
- Types, validators in configuration API
- Block find if publisher cannot be determined

## [2.0.5] - 2022-09-16
### Fixed
- Handle inventory import timeout
- Do not import campaigns from deleted servers
- Remove campaigns from deleted servers
- Remove outdated servers

## [2.0.4] - 2022-09-05
### Fixed
- Handle undefined stid

## [2.0.3] - 2022-08-16
### Fixed
- Default config values
- Supply statistics

## [2.0.2] - 2022-08-11
### Fixed
- Decrypt encrypted API keys

## [2.0.1] - 2022-08-04
### Added
- MySQL stored procedures clearing tables

## [2.0.0] - 2022-08-03
### Added
- Configuration API
### Changed
- Laravel 9
- PHP 8

## [1.18.7] - 2022-07-27
### Fixed
- Support new sizes in metaverse

## [1.18.6] - 2022-07-20
### Changed
- Add medium and vendor to stats
- Validate banners' sizes with taxonomy
### Fixed
- Clear non whitelisted campaigns

## [1.18.5] - 2022-07-08
### Added
- Inventory whitelist for adservers
### Fixed
- Default value of site classifier accepted banners setting
- Progress of fetching hosts

## [1.18.4] - 2022-06-30
### Added
- Command listing administrators
- JWT authentication for other services
### Fixed
- Do not log entered password
- Guest requests from DCL

## [1.18.3] - 2022-06-23
### Fixed
- Saving Decentraland sites' URL

## [1.18.2] - 2022-06-15
### Changed
- Command creating an administrator account returns an error code if it is unsuccessful
- Info box is set dynamically

## [1.18.1] - 2022-06-10
### Fixed
- Direct deal for metaverse

## [1.18.0] - 2022-06-02
### Changed
- Laravel 8

## [1.17.0] - 2022-05-19
### Added
- Filtering local banners in site's classifier
- User banning
### Removed
- Legacy targeting (from Taxonomy v1)
### Fixed
- Error while targeting/filtering is not cached
- Unused bonus calculation
- Decentraland and Cryptovoxels site name

## [1.16.2] - 2022-04-21
### Fixed
- Admin dashboard reports

## [1.16.1] - 2022-04-07
### Added
- Uploaded file's size validation
### Changed
- Allow editing metaverse site's address

## [1.16.0] - 2022-04-05
### Fixed
- Allow classifying models

## [1.15.0] - 2022-03-23
### Added
- Cookie3 support
- Custom metaverses support
### Changed
- Targeting uses taxonomy v2

## [1.14.2] - 2022-03-08
### Fixed
- Video ads scaling

## [1.14.1] - 2022-03-01
### Added
- Model ads support
### Fixed
- Server statistics
- Video ads streaming

## [1.14.0] - 2022-02-15
### Added
- Video ads support
### Changed
- 2FA requirement when setting password
- 2FA requirement when connecting the wallet

## [1.13.3] - 2022-02-10
### Fixed
- Add batches to classify requests

## [1.13.2] - 2022-02-10
### Added
- Decentraland integration
### Fixed
- Minor issues

## [1.13.1] - 2022-01-28
### Fixed
- Default zone mode

## [1.13.0] - 2022-01-28
### Added
- Ad's MIME type
- Decentraland support

## [1.12.0] - 2022-01-19
### Added
- Filtering for only accepted banners
- Auto CPM setting
- Search by campaign & site UUID

## [1.11.2] - 2022-01-13
### Added
- Withdrawal to the cryptocurrency wallet
### Fixed
- Mail data serialization
- Omit mails to anonymous users

## [1.11.1] - 2022-01-10
### Added
- Create an account with the cryptocurrency wallet
### Changed
- Upgrade to Composer 2.2.3
### Fixed
- Rollback of DB migration (deletion of bid strategies)

## [1.11.0] - 2021-12-31
### Added
- Connecting your account with the cryptocurrency wallet
- Log in to your account with the cryptocurrency wallet
- Auto registration
- Auto withdrawal
### Changed
- Add parcel id to the event context

## [1.10.9] - 2021-12-09
### Added
- Moderator role
- Agency role
 
## [1.10.8] - 2021-10-18
### Added
- Detecting metamask

## [1.10.7] - 2021-10-07
### Changed
- Allow to set default rank for present and missing category
- Allow editing open ended categories eg. domains

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

[Unreleased]: https://github.com/adshares/adserver/compare/v2.4.12...develop
[2.4.12]: https://github.com/adshares/adserver/compare/v2.4.11...v2.4.12
[2.4.11]: https://github.com/adshares/adserver/compare/v2.4.10...v2.4.11
[2.4.10]: https://github.com/adshares/adserver/compare/v2.4.9...v2.4.10
[2.4.9]: https://github.com/adshares/adserver/compare/v2.4.8...v2.4.9
[2.4.8]: https://github.com/adshares/adserver/compare/v2.4.7...v2.4.8
[2.4.7]: https://github.com/adshares/adserver/compare/v2.4.6...v2.4.7
[2.4.6]: https://github.com/adshares/adserver/compare/v2.4.5...v2.4.6
[2.4.5]: https://github.com/adshares/adserver/compare/v2.4.4...v2.4.5
[2.4.4]: https://github.com/adshares/adserver/compare/v2.4.3...v2.4.4
[2.4.3]: https://github.com/adshares/adserver/compare/v2.4.2...v2.4.3
[2.4.2]: https://github.com/adshares/adserver/compare/v2.4.1...v2.4.2
[2.4.1]: https://github.com/adshares/adserver/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/adshares/adserver/compare/v2.3.2...v2.4.0
[2.3.2]: https://github.com/adshares/adserver/compare/v2.3.1...v2.3.2
[2.3.1]: https://github.com/adshares/adserver/compare/v2.3.0...v2.3.1
[2.3.0]: https://github.com/adshares/adserver/compare/v2.2.7...v2.3.0
[2.2.7]: https://github.com/adshares/adserver/compare/v2.2.6...v2.2.7
[2.2.6]: https://github.com/adshares/adserver/compare/v2.2.5...v2.2.6
[2.2.5]: https://github.com/adshares/adserver/compare/v2.2.4...v2.2.5
[2.2.4]: https://github.com/adshares/adserver/compare/v2.2.3...v2.2.4
[2.2.3]: https://github.com/adshares/adserver/compare/v2.2.2...v2.2.3
[2.2.2]: https://github.com/adshares/adserver/compare/v2.2.1...v2.2.2
[2.2.1]: https://github.com/adshares/adserver/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/adshares/adserver/compare/v2.1.1...v2.2.0
[2.1.1]: https://github.com/adshares/adserver/compare/v2.1.0...v2.1.1
[2.1.0]: https://github.com/adshares/adserver/compare/v2.0.5...v2.1.0
[2.0.5]: https://github.com/adshares/adserver/compare/v2.0.4...v2.0.5
[2.0.4]: https://github.com/adshares/adserver/compare/v2.0.3...v2.0.4
[2.0.3]: https://github.com/adshares/adserver/compare/v2.0.2...v2.0.3
[2.0.2]: https://github.com/adshares/adserver/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/adshares/adserver/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/adshares/adserver/compare/v1.18.7...v2.0.0
[1.18.7]: https://github.com/adshares/adserver/compare/v1.18.6...v1.18.7
[1.18.6]: https://github.com/adshares/adserver/compare/v1.18.5...v1.18.6
[1.18.5]: https://github.com/adshares/adserver/compare/v1.18.4...v1.18.5
[1.18.4]: https://github.com/adshares/adserver/compare/v1.18.3...v1.18.4
[1.18.3]: https://github.com/adshares/adserver/compare/v1.18.2...v1.18.3
[1.18.2]: https://github.com/adshares/adserver/compare/v1.18.1...v1.18.2
[1.18.1]: https://github.com/adshares/adserver/compare/v1.18.0...v1.18.1
[1.18.0]: https://github.com/adshares/adserver/compare/v1.17.0...v1.18.0
[1.17.0]: https://github.com/adshares/adserver/compare/v1.16.2...v1.17.0
[1.16.2]: https://github.com/adshares/adserver/compare/v1.16.1...v1.16.2
[1.16.1]: https://github.com/adshares/adserver/compare/v1.16.0...v1.16.1
[1.16.0]: https://github.com/adshares/adserver/compare/v1.15.0...v1.16.0
[1.15.0]: https://github.com/adshares/adserver/compare/v1.14.2...v1.15.0
[1.14.2]: https://github.com/adshares/adserver/compare/v1.14.1...v1.14.2
[1.14.1]: https://github.com/adshares/adserver/compare/v1.14.0...v1.14.1
[1.14.0]: https://github.com/adshares/adserver/compare/v1.13.3...v1.14.0
[1.13.3]: https://github.com/adshares/adserver/compare/v1.13.2...v1.13.3
[1.13.2]: https://github.com/adshares/adserver/compare/v1.13.1...v1.13.2
[1.13.1]: https://github.com/adshares/adserver/compare/v1.13.0...v1.13.1
[1.13.0]: https://github.com/adshares/adserver/compare/v1.12.0...v1.13.0
[1.12.0]: https://github.com/adshares/adserver/compare/v1.11.2...v1.12.0
[1.11.2]: https://github.com/adshares/adserver/compare/v1.11.1...v1.11.2
[1.11.1]: https://github.com/adshares/adserver/compare/v1.11.0...v1.11.1
[1.11.0]: https://github.com/adshares/adserver/compare/v1.10.9...v1.11.0
[1.10.9]: https://github.com/adshares/adserver/compare/v1.10.8...v1.10.9
[1.10.8]: https://github.com/adshares/adserver/compare/v1.10.7...v1.10.8
[1.10.7]: https://github.com/adshares/adserver/compare/v1.10.6...v1.10.7
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

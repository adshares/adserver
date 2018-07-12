[![Quality Status](https://sonarcloud.io/api/project_badges/measure?project=adshares-adserver&metric=alert_status)](https://sonarcloud.io/dashboard?id=adshares-adserver "Master")
[![Build Status](https://travis-ci.org/adshares/adserver.svg?branch=master)](https://travis-ci.org/adshares/adserver "Master")
[![Build Status](https://travis-ci.org/adshares/adserver.svg?branch=develop)](https://travis-ci.org/adshares/adserver "Develop")

# README @ adshares/server

Blockchain Revolution in Advertising

## Technical information

  * Part of the Adshares Advertising Ecosystem
  * All up to date technical information required to configure environment are availble through our [dockerization](https://github.com/adshares/dockerization) project

## Adserver & Adpanel - integration development/testing

  Requires Linux for such a quick setup, Windows will work with some hacks, like proxy (?)

  * clone our:
    * [dockerization](https://github.com/adshares/dockerization)
    * [adserver](https://github.com/adshares/adserver)
    * [adpanel](https://github.com/adshares/adpanel)
      * ``mkdir adshares && cd adshares && for i in dockerization adserver adpanel; do git clone git@github.com:adshares/$i.git; done && ls -l``
  * cd dockerization
  * copy contents of dockers/dev/hostnames-dockers into /etc/hosts
  * link dev/adserver and dev/adpanel docker dev/ projects with your local dev repos
    * using ``./console.sh link`` command
    * if in doubt check ``./console.sh help`` && ``./console.sh examples``
    * [for i in ......done](http://tldp.org/HOWTO/Bash-Prog-Intro-HOWTO.html)
  * then build adpanel with dev environment
    * ``./console.sh -s angularenv=dev b dev/adpanel``
  * then build adserve with resetdata=1 setting, it will migrate:refresh each time you will use ./console up dev/Adserver
    * ``./console.sh -s resetdata=1 b dev/adserver``
  * also build mailcatcher (does not require repolinking)
    * ``./console.sh b dev/mailcatcher``
  * then up all projects
    * ``./console.sh u dev/mailcatcher,adserver,adpanel``

    you should be able to run http://panel.ads and http://mailcatcher.ads


## Adserver, Ads, Adselect, Adpay, Aduser - integration development/testing

  * clone our:
    * [dockerization](https://github.com/adshares/dockerization)
    * [adserver](https://github.com/adshares/adserver)
    * [adselect](https://github.com/adshares/adselect)
    * [aduser](https://github.com/adshares/aduser)
    * [adpay](https://github.com/adshares/adpay)
    * [ads](https://github.com/adshares/ads)
    * [test-website](https://github.com/adshares/test-website)
      * ``mkdir adshares && cd adshares && for i in dockerization adserver adselect aduser adpay ads test-website; do git clone git@github.com:adshares/$i.git; done && ls -l``
  * cd dockerization
  * copy contents of dockers/dev/hostnames-dockers into /etc/hosts
  * link dev/(adserver, adselect, aduser, adpay, ads and test-website) docker dev/ projects with your local dev repos
    * using ``./console.sh link`` command
    * if in doubt check ``./console.sh help`` && ``./console.sh examples``
    * [for i in ......done](http://tldp.org/HOWTO/Bash-Prog-Intro-HOWTO.html)
  * then build adserve with resetdata=1 & mockdata=1 settings, it will migrate:refresh and mock some data each time you will use ./console up dev/adserver
    * ``./console.sh -s resetdata=1,mockdata=1 b dev/adserver``
  * build rest of the projects
    * ``./console.sh b dev/adselect,aduser,adpay,ads,test-website``
  * then up all projects
    * ``./console.sh u dev/adserver,adselect,aduser,adpay,ads,test-website``

  you should be able to run http://publisher.ads in your browser and get some banners

## Issue Board

  * [Issue Board](https://github.com/adshares/adserver#issue-sh-boards)
    * Requires : [issue.sh for GitHub](https://issue.sh)

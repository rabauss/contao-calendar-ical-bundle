# Changelog

## [5.0.0](https://github.com/rabauss/contao-calendar-ical-bundle/compare/v5.1.1...v5.0.0) (2024-03-06)


### Features

* add github ci and release-please ([2b04aab](https://github.com/rabauss/contao-calendar-ical-bundle/commit/2b04aabaa45da8cd8ec21f9758600004b6ef7877))
* add regenerate ics files to backend maintenance module ([f288388](https://github.com/rabauss/contao-calendar-ical-bundle/commit/f2883882eaa563214c44403b5dbe9957a92671d6))
* better handling for existing events. events are not fully deleted and reimported but mapped via the event uid, fix ecs and phpstan findings ([0fe487b](https://github.com/rabauss/contao-calendar-ical-bundle/commit/0fe487bbb68bdc5df56fdf0ef46796f1a11405f4))
* export as ics file, delete ics file if calendar is deleted ([83081d1](https://github.com/rabauss/contao-calendar-ical-bundle/commit/83081d1d856f63f2dcc1eee8c7af3120205cae49))


### Bug Fixes

* add backend assets via EventSubscriber ([6d66793](https://github.com/rabauss/contao-calendar-ical-bundle/commit/6d667938170de806435101ecfc3d1b49dda6c8b0))
* change icon ([21d6dc4](https://github.com/rabauss/contao-calendar-ical-bundle/commit/21d6dc455b0b76b6c35ad0e26b73262fd12dd7a4))
* copy link to clipboard fix for contao 5 ([c052b34](https://github.com/rabauss/contao-calendar-ical-bundle/commit/c052b34ed317d023714b179289ba2a361e5dcc90))
* default values are closures in contao 5 ([ddedb47](https://github.com/rabauss/contao-calendar-ical-bundle/commit/ddedb4718bf57cf49f951539fb503ff748758da8))
* default values are closures in contao 5 ([7a34e1b](https://github.com/rabauss/contao-calendar-ical-bundle/commit/7a34e1bde7a073e46b091dc63888c2d9fb01d7eb))
* don't store the uid in the description field ([4a0af93](https://github.com/rabauss/contao-calendar-ical-bundle/commit/4a0af93e1603e484f90b30ab8c2011021fb6011b))
* ecs and phpstan findings ([10739cc](https://github.com/rabauss/contao-calendar-ical-bundle/commit/10739cc3bcc0d144257b14c0f4c3ec687348131b))
* ecs and phpstan fixes ([391e851](https://github.com/rabauss/contao-calendar-ical-bundle/commit/391e85152ef8ea50acaae8688e2fa717d15eeed7))
* fix database migration via contao-manager in contao 5 ([31ac4ac](https://github.com/rabauss/contao-calendar-ical-bundle/commit/31ac4ac93664e3ba93643f064543cb72b9bf6b18))
* fix ecs errors ([8cb6e38](https://github.com/rabauss/contao-calendar-ical-bundle/commit/8cb6e38291f2dabed3cdb669ec2d11bb747b6e6e))
* fix error during ics cache handling ([5436404](https://github.com/rabauss/contao-calendar-ical-bundle/commit/5436404fc8c3317d8d9064a29eb6903ea0f647da))
* fix error in csv import ([a617f98](https://github.com/rabauss/contao-calendar-ical-bundle/commit/a617f9884a0cff0e0e2ee5d1e4807c017d2c6063))
* fix ical download element ([182d6ec](https://github.com/rabauss/contao-calendar-ical-bundle/commit/182d6ec3b098f1d35b1f4f9e8f2e0dc865feba2b))
* fix ical download element ([094ef79](https://github.com/rabauss/contao-calendar-ical-bundle/commit/094ef7902b475ec603303ca4acb81a430cfbf407))
* fix ics import ([536f69f](https://github.com/rabauss/contao-calendar-ical-bundle/commit/536f69fa4d038f8d30986d4fcf0cf5ebd9357689))
* fix phpstan errors ([067783f](https://github.com/rabauss/contao-calendar-ical-bundle/commit/067783f30c114992e21d70523272986096a2fdc3))
* fix some minor issues ([a66e841](https://github.com/rabauss/contao-calendar-ical-bundle/commit/a66e8414c198712dccb6f6f8026ec63c35aecb4f))
* fix some minor issues ([4f60f9c](https://github.com/rabauss/contao-calendar-ical-bundle/commit/4f60f9c73b0537325f305db9a952b1fbfba4d67d))
* multiple fixes for ics export ([a587c80](https://github.com/rabauss/contao-calendar-ical-bundle/commit/a587c8002cd36769d12ea45a9469d2322ae7440f))
* only export event if a startDate (or time) is present ([f9d5558](https://github.com/rabauss/contao-calendar-ical-bundle/commit/f9d5558018527d84564bffc7d0576df9e4165b98))
* small fix for ics import ([28fb22c](https://github.com/rabauss/contao-calendar-ical-bundle/commit/28fb22ca2520408034701610767e1d52c8c31920))


### Miscellaneous Chores

* add github templates ([4257b4a](https://github.com/rabauss/contao-calendar-ical-bundle/commit/4257b4a94359350e730d80dbc455a00fb693f803))
* automatic rector changes ([d41ed3c](https://github.com/rabauss/contao-calendar-ical-bundle/commit/d41ed3cfd0b5ad6570f201eda0104c0bfc193b9f))
* bundle is loading fine, dca seems to be correct ([778c5eb](https://github.com/rabauss/contao-calendar-ical-bundle/commit/778c5eb48720a18fe9ca2afa813f761e2741b6c9))
* change .gitignore ([3b3fa26](https://github.com/rabauss/contao-calendar-ical-bundle/commit/3b3fa26cc13e99ff53327348a63857251e83c3cf))
* **ci:** add php 8.3 to version matrix ([e0c8f12](https://github.com/rabauss/contao-calendar-ical-bundle/commit/e0c8f12cee00db18a0746d9d12de8f996f942fd5))
* fix phpstan errors ([ed2998f](https://github.com/rabauss/contao-calendar-ical-bundle/commit/ed2998f31209d79e46b8b9086e873b0071185b4a))
* **main:** release 5.0.0 ([ca0c461](https://github.com/rabauss/contao-calendar-ical-bundle/commit/ca0c461db3ac553265d2b976edb49ec2b7657ed4))
* **main:** release 5.0.1 ([a7f1aa0](https://github.com/rabauss/contao-calendar-ical-bundle/commit/a7f1aa0ea74ccd3c21ef1932068aff9e6c19fc7c))
* **main:** release 5.0.2 ([8aa5ccd](https://github.com/rabauss/contao-calendar-ical-bundle/commit/8aa5ccd56282e4bb2ac7ac193f4759d295ed0974))
* **main:** release 5.1.0 ([c99ce03](https://github.com/rabauss/contao-calendar-ical-bundle/commit/c99ce037c088fb140fe2b747191555b27f217863))
* **main:** release 5.1.1 ([8343e1f](https://github.com/rabauss/contao-calendar-ical-bundle/commit/8343e1f09f8ed0b639145dcd8a321a7cd39e6473))


### Documentation

* latest fixes to README ([9f2f15b](https://github.com/rabauss/contao-calendar-ical-bundle/commit/9f2f15b40a13d0fc81e7e5ab56c1fae22158ecfd))

## [5.1.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.1.0...v5.1.1) (2024-03-06)


### Bug Fixes

* fix some minor issues ([a66e841](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/a66e8414c198712dccb6f6f8026ec63c35aecb4f))

## [5.1.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.0.2...v5.1.0) (2024-03-05)


### Features

* better handling for existing events. events are not fully deleted and reimported but mapped via the event uid, fix ecs and phpstan findings ([0fe487b](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/0fe487bbb68bdc5df56fdf0ef46796f1a11405f4))


### Bug Fixes

* ecs and phpstan findings ([10739cc](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/10739cc3bcc0d144257b14c0f4c3ec687348131b))


### Miscellaneous Chores

* automatic rector changes ([d41ed3c](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/d41ed3cfd0b5ad6570f201eda0104c0bfc193b9f))
* **ci:** add php 8.3 to version matrix ([e0c8f12](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/e0c8f12cee00db18a0746d9d12de8f996f942fd5))
* fix phpstan errors ([ed2998f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/ed2998f31209d79e46b8b9086e873b0071185b4a))

## [5.0.2](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.0.1...v5.0.2) (2023-11-28)


### Bug Fixes

* don't store the uid in the description field ([4a0af93](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4a0af93e1603e484f90b30ab8c2011021fb6011b))

## [5.0.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.0.0...v5.0.1) (2023-11-26)


### Bug Fixes

* default values are closures in contao 5 ([ddedb47](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/ddedb4718bf57cf49f951539fb503ff748758da8))
* fix database migration via contao-manager in contao 5 ([31ac4ac](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/31ac4ac93664e3ba93643f064543cb72b9bf6b18))

## [5.0.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/4.5.1...v5.0.0) (2023-11-25)


### Features

* add github ci and release-please ([2b04aab](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/2b04aabaa45da8cd8ec21f9758600004b6ef7877))
* add regenerate ics files to backend maintenance module ([f288388](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/f2883882eaa563214c44403b5dbe9957a92671d6))
* export as ics file, delete ics file if calendar is deleted ([83081d1](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/83081d1d856f63f2dcc1eee8c7af3120205cae49))


### Bug Fixes

* add backend assets via EventSubscriber ([6d66793](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/6d667938170de806435101ecfc3d1b49dda6c8b0))
* change icon ([21d6dc4](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/21d6dc455b0b76b6c35ad0e26b73262fd12dd7a4))
* copy link to clipboard fix for contao 5 ([c052b34](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/c052b34ed317d023714b179289ba2a361e5dcc90))
* default values are closures in contao 5 ([7a34e1b](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/7a34e1bde7a073e46b091dc63888c2d9fb01d7eb))
* ecs and phpstan fixes ([391e851](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/391e85152ef8ea50acaae8688e2fa717d15eeed7))
* fix ecs errors ([8cb6e38](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/8cb6e38291f2dabed3cdb669ec2d11bb747b6e6e))
* fix error during ics cache handling ([5436404](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/5436404fc8c3317d8d9064a29eb6903ea0f647da))
* fix error in csv import ([a617f98](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/a617f9884a0cff0e0e2ee5d1e4807c017d2c6063))
* fix ical download element ([182d6ec](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/182d6ec3b098f1d35b1f4f9e8f2e0dc865feba2b))
* fix ical download element ([094ef79](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/094ef7902b475ec603303ca4acb81a430cfbf407))
* fix ics import ([536f69f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/536f69fa4d038f8d30986d4fcf0cf5ebd9357689))
* fix phpstan errors ([067783f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/067783f30c114992e21d70523272986096a2fdc3))
* fix some minor issues ([4f60f9c](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4f60f9c73b0537325f305db9a952b1fbfba4d67d))
* multiple fixes for ics export ([a587c80](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/a587c8002cd36769d12ea45a9469d2322ae7440f))
* only export event if a startDate (or time) is present ([f9d5558](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/f9d5558018527d84564bffc7d0576df9e4165b98))
* small fix for ics import ([28fb22c](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/28fb22ca2520408034701610767e1d52c8c31920))


### Miscellaneous Chores

* add github templates ([4257b4a](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4257b4a94359350e730d80dbc455a00fb693f803))
* bundle is loading fine, dca seems to be correct ([778c5eb](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/778c5eb48720a18fe9ca2afa813f761e2741b6c9))
* change .gitignore ([3b3fa26](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/3b3fa26cc13e99ff53327348a63857251e83c3cf))


### Documentation

* latest fixes to README ([9f2f15b](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/9f2f15b40a13d0fc81e7e5ab56c1fae22158ecfd))

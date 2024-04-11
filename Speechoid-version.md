# Speechoid version changelog

## About

Speechoid is the speech synthesis backend used by the Wikispeech extension. It is a collection
of components accessed via Wikispeech-server. The Speechoid version represents a tag on the
virtual package of all the components within Speechoid.

Any update of the components within Speechoid
must trigger a version bump of the virtual package.

Any update of the Wikispeech extension that require a greater Speechoid version
must trigger a version bump of the Wikispeech extension.

Speechoid use [Semantic versioning](https://semver.org/). Major.Minor.Patch.

For each version of Speechoid we list the commit id
of [all components in the downstream git repositories](https://gerrit.wikimedia.org/r/admin/repos/q/filter:mediawiki%252Fservices%252Fwikispeech%252F).

## Versions

### 0.1.4-SNAPSHOT
YYYY-MM-DD

#### Components
* MaryTTS @commit id
* Mishkal @commit id
* Pronlex @commit id
* Symbolset @commit id
* Wikispeech-server @commit id
* Sox proxy @commit id

#### Events
* No major events.

### 0.1.3
2024-03-25

#### Components
* MaryTTS @commit ad87696b1c6c16fd9cffad603ec5c9c1626f75d6
* Mishkal @commit 319e83b7345047ec0639c1947aa7e38c1ab43a2c
* Pronlex @commit a9cc527bda93dff8faebec0822b96008d34e6ca5
* Symbolset @commit 1b5872c85b8c50a5ac7403aa6b12d8bb7e2fe434
* Wikispeech-server @commit 24717fd5304df91b79dcde54121a7c49cd589233
* Sox proxy @commit f180bf166b09fe35d62accf0d7d790fb7d4be9e4

#### Events
* Add Sox proxy component.
* Update Blubber pipeline.
* Pronlex: [T280237](https://phabricator.wikimedia.org/T280237) Configure to use MariaDB or SQLite using env variable
* Wikispeech server: Add HAProxy
* Wikispeech server: Only remove the temporary files related to an utterance when it's done
* Wikispeech server: Support for ipa ssml

### 0.1.2
2020-09-11

#### Components
* MaryTTS @commit 0e4c2013fd74d4ea8a097e352ae3914571104aed
* Mishkal @commit cbfb935041a37a1b11be2fbdc1ee22bb2de762ec
* Pronlex @commit f81f664596a694bb742d7ed1e92d581262ec9764
* Symbolset @commit c4722213c7ea28748b583c5ed11c3ca73234aed9
* Wikispeech-server @commit e9e5efbf22f039780f568fc7ac968dbda25c0c7a

#### Events
* [T260293](https://phabricator.wikimedia.org/T260293) Wikispeech-server responds with integer milliseconds rather than floating seconds.
* [T262655](https://phabricator.wikimedia.org/T262655) Version changelog file introduced.

### 0.1.1
2020-09-11

* Version 0.1.1 and earlier is not documented in this file.


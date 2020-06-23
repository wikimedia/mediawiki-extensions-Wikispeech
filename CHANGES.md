# Wikispeech version changelog

## About

Wikispeech use [Semantic versioning](https://semver.org/). Major.Minor.Patch.

## Versioning

Add new entries to the top of current -SNAPSHOT section,
i.e. in reversed chronological order.

Annotate your code with @since using the current -SNAPSHOT version.
E.g. when the current is 0.1.2-SNAPSHOT, use @since 0.1.2 in the code.

## On release

Remove -SNAPSHOT, set date and create a new -SNAPSHOT section.

If version bump is greater than originally expected,
e.g. from 0.1.2-SNAPSHOT to 0.2.0,
then replace all @since 0.1.2 tags in the code to 0.2.0 using a new task.

Update [mediawiki.org documentation](https://www.mediawiki.org/wiki/Extension:Wikispeech)
the match the new release version.

## Versions

### 0.1.5-SNAPSHOT
202Y-MM-DD

* [T247395](https://phabricator.wikimedia.org/T247395) Limit input length in Speechoid requests.
* [T248825](https://phabricator.wikimedia.org/T248825) Clean up segmenting
* [T248469](https://phabricator.wikimedia.org/T248469) Create database for utterance data.
* [T181780](https://phabricator.wikimedia.org/T181780) Use OOUI for the player controls.

### 0.1.4
2020-05-19

* [T248472](https://phabricator.wikimedia.org/T248472) Create segment hasher.
* [T246079](https://phabricator.wikimedia.org/T246079) Add cache to segmenter.
* [T249198](https://phabricator.wikimedia.org/T249198) Version changelog file introduced.

### 0.1.3
2018-09-12

* Version 0.1.3 and earlier is not documented in this file.

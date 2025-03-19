# Developer files
This directory contains miscellaneous scripts and instructions useful for
developing and testing Wikispeech.

*   `10-wikispeech.php` - MediaWiki settings. Can be copied to or imported in LocalSettings.php.
*   `speechoid-docker-compose/` - All you need to run Speechoid on your machine.

When adding a script, also add it to this list with a short description.

## Naming PHPUnit tests
For readability and understanding amongst developers, we decided on a naming convention for PHPUnit tests which follows the pattern: `testFunction_Inputs_expectedResult`.
This doesn't necessarily apply when using [data providers](https://docs.phpunit.de/en/10.5/writing-tests-for-phpunit.html#data-providers) and integration tests.

For tests using `@dataProvider`, the pattern becomes either
`testFunction` or `testFunction_expectedResult`
e.g. `testIsHex` or `testSegmentSentences_dontSegment`.

The test method name is often divided into three parts:
1. Describing the function to test
2. Inputs or context
3. The expected result, should be an imperative clause (an urge)

#### Examples:
- `testOnApiCheckCanExecute_UserHasPermission_returnTrue`
- `testCleanHtml_nodesOnSameLevel_generatePath`
- `testApiRequest_removeTagsNotAnObject_throwException`

# Wikispeech

Wikispeech is a [MediaWiki extension](https://www.mediawiki.org/wiki/Manual:Extensions)
which provides text-to-speech functionality allowing a page to be read aloud to
the user.

It uses the Speechoid service to synthesize audio from the text on MediaWiki pages.

For more information about the background to the project, check out the
[Wikispeech](https://meta.wikimedia.org/wiki/Wikispeech) page on Meta.


## Developing and installing

For information on installing Wikispeech on your wiki, please
see <https://www.mediawiki.org/wiki/Extension:Wikispeech>.


## Funding Acknowledgements

This work was supported by the Swedish Post and Telecom Authority
(PTS) through the grant "Wikispeech – en användargenererad talsyntes
på Wikipedia" (2016–2017).

This work was supported by the Swedish Post and Telecom Authority
(PTS) through the grant "Talresursinsamlaren – För ett tillgängligare
Wikipedia genom Wikispeech" (2019–2021).

This work was supported by the Swedish Inheritance Fund (Allmänna arvsfonden) through the grant "Wikispeech - Talsyntes och taldatainsamlare" (2024–2027).

## Good to know

### Maintenance scripts - How and when to use them
Under the folder `maintenance` you can find our maintenance scripts. This section is a documentation on how and when to use each of them, as you will probably need some of them when working on Wikispeech.

#### benchmark.php
Used for evaluating resource-usage metrics when running Wikispeech and Speechoid on a page. Useful for performance checks after changes to segmentation or synthesis, and for comparing output size across different pages, languages, and voices.

Examples: 
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/benchmark.php -p "Main_Page"`
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/benchmark.php -p "Main_Page" -l en -v dfki-spike-hsmm -t 300`

#### flushUtterances.php
Script to manually execute `UtteranceStore` flush methods. It will flush cached utterances from the database rows and files. Useful when testing synthesis or debugging outdated utterances after code changes. Supports flushing by expiration, language or page, either immediately with force (-f), or by queuing a job when force is left out.

Example:
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/flushUtterances.php -l en -f`

#### flushUtterancesByExpirationDateOnFile.php
Script to manually execute `UtteranceStore::flushUtterancesByExpirationDateOnFile()`.
Useful for cleaning up old/orphaned .opus/.json files in the utterance storage. Runs immediately with force (-f), otherwise queues a job.

Example: 
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/flushUtterancesByExpirationDateOnFile.php -f`

#### generatePageFile.php
Script to generate audio files for pages. Generates a single audio file for a wiki page (e.g, for users to listen to an article offline). It synthesizes the page in the chosen language and requires `opusdec` and `opusenc` to be installed.
See https://opus-codec.org

Example: 
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/generatePageFile.php -p "Barack_Obama" -l en`

#### populateSpeechoidLexiconFromWiki.php
Maintenance script to populate speechoid lexicon
with entries from wiki. Useful after setting up a new Speechoid instance, or if Speechoid lexicon entries are missing and need to be recreated.

Example:
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/populateSpeechoidLexiconFromWiki.php --user "Admin"`

#### preSynthesizeMessages.php
Maintenance script to pre synthesize selected system or error messages and stores them as utterances. Useful to ensure that critical messages (e.g, error prompts) are available even if synthesis fails, for example when Speechoid is down.

Examples: 
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/preSynthesizeMessages.php -l en`
- `php maintenance/run.php ./extensions/Wikispeech/maintenance/preSynthesizeMessages.php -l en -v dfki-spike-hsmm`

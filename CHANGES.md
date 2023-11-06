# Wikispeech version changelog

## About

Wikispeech use [Semantic versioning](https://semver.org/). Major.Minor.Patch.

## Versioning

Add new entries to the top of current -SNAPSHOT section,
i.e. in reversed chronological order.

Annotate your code with @since using the current -SNAPSHOT version.
E.g. when the current is 0.1.2-SNAPSHOT, use @since 0.1.2 in the code.

If updating Speechoid requirements then also update `dev/speechoid-docker-compose`
to match that version. I.e. update the docker image tags.

## On release

Remove -SNAPSHOT, set date and create a new -SNAPSHOT section.

If version bump is greater than originally expected,
e.g. from 0.1.2-SNAPSHOT to 0.2.0,
then replace all @since 0.1.2 tags in the code to 0.2.0 using a new task.

Update [mediawiki.org documentation](https://www.mediawiki.org/wiki/Extension:Wikispeech)
the match the new release version.

Update version in extension.json.

## Versions

### 0.1.11-SNAPSHOT
YYYY-MM-DD
Speechoid: 0.1.2

* [T339840](https://phabricator.wikimedia.org/T339840) Editor: Redirect logged out users to login page if they lack right to edit lexicon
* [T276332](https://phabricator.wikimedia.org/T276332) Editor: disable preview to indicate that the system is busy
* [T276332](https://phabricator.wikimedia.org/T276332) Simplify message for error when mapping transcriptions
* [T279412](https://phabricator.wikimedia.org/T279412) Change label of submit button in lexicon editor to reflect what it does

### 0.1.10
2023-03-08
Speechoid: 0.1.2

* [T255152](https://phabricator.wikimedia.org/T255152) Fix newline CleanedText's being added at the end when cleaning
* [T322402](https://phabricator.wikimedia.org/T322402) Only namespace is included in title segment if it's present in the title
* [T279774](https://phabricator.wikimedia.org/T279774) LexiconEditor should handle internal api error
* [T284060](https://phabricator.wikimedia.org/T284060) Allow setting "preferred" in Special:EditLexicon
* [T285048](https://phabricator.wikimedia.org/T285048) Introduce class Utterance
* [T300104](https://phabricator.wikimedia.org/T300104) Sort out footer toolbar interaction
* [T299994](https://phabricator.wikimedia.org/T299994) Buttons do not show up in the player UI
* [T300120](https://phabricator.wikimedia.org/T300120) Failing Jenkins tests for mw.loader
* [T293578](https://phabricator.wikimedia.org/T293578) Skip metrics logging for pre-rendered pages
* [T286579](https://phabricator.wikimedia.org/T286579) Introduced optional metrics logging in ApiWikispeechListen.
* [T285141](https://phabricator.wikimedia.org/T285141) Major refactor of Segmenter.
* [T286198](https://phabricator.wikimedia.org/T286198) Empty node causes Speechoid error
* [T286372](https://phabricator.wikimedia.org/T286372) Introduced pre-Speechoid text processors that transform numbers, years and dates to Swedish text.
* [T284138](https://phabricator.wikimedia.org/T284138) Playback stalled if utterance fail to load when prepared by previous utterance starting to play.
* [T281898](https://phabricator.wikimedia.org/T281898) Use connection queue against Speechoid TTS operations.
* [T283063](https://phabricator.wikimedia.org/T283063) Title highlighting is wrong.

### 0.1.9
2021-05-27
Speechoid: 0.1.2

* [T282151](https://phabricator.wikimedia.org/T282151) UI for user preferences.
* [T281803](https://phabricator.wikimedia.org/T281803) Handle gadget consumer URL in utterance store.
* [T282513](https://phabricator.wikimedia.org/T282513) Config variable for producer mode.
* [T281667](https://phabricator.wikimedia.org/T281667) Load user options from consumer wiki.
* [T281665](https://phabricator.wikimedia.org/T281665) Load modules and settings.
* [T281663](https://phabricator.wikimedia.org/T281663) Communication between consumer and producer.
* [T280233](https://phabricator.wikimedia.org/T280233) Control creation of LexiconStorage from config in extension.json.
* [T281262](https://phabricator.wikimedia.org/T281262) Treat Speechoid lexicon identity as int.
* [T279632](https://phabricator.wikimedia.org/T279632) Introduce LexiconWikiStorage.
* [T277242](https://phabricator.wikimedia.org/T277242) Ensure new lexicon edits are CC0.
* [T277153](https://phabricator.wikimedia.org/T277153) Allow user to decide on updating or adding new entry if entry with same spelling exists.
* [T280244](https://phabricator.wikimedia.org/T280244) Optionally mark utterance files with TTL in Swift.
* [T133857](https://phabricator.wikimedia.org/T133857) Only add edit button to the player if user has right to edit lexicon.
* [T280239](https://phabricator.wikimedia.org/T280239) Use GET requests for segments and utterances.

### 0.1.8
2021-04-01
Speechoid: 0.1.2

* [T275756](https://phabricator.wikimedia.org/T275756) Add trigger for purging the UtteranceStore upon lexicon edit.
* [T133857](https://phabricator.wikimedia.org/T133857) Link to Special:EditLexicon from player.
* [T274649](https://phabricator.wikimedia.org/T274649) Add lexicon entries using Special:EditLexicon.
* [T277145](https://phabricator.wikimedia.org/T277145) Implement lexicon delete features.
* [T274504](https://phabricator.wikimedia.org/T274504) Introduce LexiconHandler.
* [T274486](https://phabricator.wikimedia.org/T274486) Preview transcription in lexicon editor.
* [T274645](https://phabricator.wikimedia.org/T274645) Create edit lexicon special page.
* [T133872](https://phabricator.wikimedia.org/T133872) A user right for corrections to the lexicon of pronunciations.
* [T265335](https://phabricator.wikimedia.org/T265335) Introduce namespaces to extension Wikispeech.
* [T265130](https://phabricator.wikimedia.org/T265130) Switch from static scope hooks to instance scope hooks with injections.
* [T264403](https://phabricator.wikimedia.org/T264403) Speechoid/Wikispeech benchmark maintenance script.
* [T263112](https://phabricator.wikimedia.org/T263112) Listening action should not be available in non-read view.
* [T261288](https://phabricator.wikimedia.org/T261288) Add option to deactivate and reactivate player in menu.

### 0.1.7
2020-10-19
Speechoid: 0.1.2

* [T265335](https://phabricator.wikimedia.org/T265335) Introduce PHP namespaces.
* [T264899](https://phabricator.wikimedia.org/T264899) Introduce configurable response timeout in SpeechoidConnector.
* [T263115](https://phabricator.wikimedia.org/T263115) Break out getDefaultVoice() to separate class.
* [T260905](https://phabricator.wikimedia.org/T260905) Refactored (shortened) name of flushUtterancesByExpirationDateOnFileFromFileBackend.
  @since tags on renamed methods and classes is bumped from 0.1.5 to 0.1.7,
  but due to the pre-release state of the project
  we did not keep and deprecate the previous method names.
* [T251261](https://phabricator.wikimedia.org/T251261) Mechanisms for executing flushing of utterances from the store.
* [T254737](https://phabricator.wikimedia.org/T254737) Rename APIs to wikispeech-listen and wikispeech-segment.

### 0.1.6
2020-09-22
Speechoid: 0.1.2

* [T262387](https://phabricator.wikimedia.org/T262387) Update to use Speechoid 0.1.2.
* [T262948](https://phabricator.wikimedia.org/T262948) Request default voice per language from Speechoid.
* [T261753](https://phabricator.wikimedia.org/T261753) Allow listening to page even if edited.
* [T262655](https://phabricator.wikimedia.org/T262655) Versioning of Speechoid.
* [T257078](https://phabricator.wikimedia.org/T257078) Trigger Wikispeech UI on PageContentLanguage, not interface language.

### 0.1.5
2020-09-03
Speechoid: 0.1.1

* [T248162](https://phabricator.wikimedia.org/T248162) Use revision and segment ID as input for synthesizing speech.
* [T260891](https://phabricator.wikimedia.org/T260891) Allow historical revisions to be accessed in Segmenter cache.
* [T260875](https://phabricator.wikimedia.org/T260875) Enable using default parameters for segmenting.
* [T255001](https://phabricator.wikimedia.org/T255001) Schedulable cleanup job of orphaned utterance files in file backend.
* [T257571](https://phabricator.wikimedia.org/T257571) Extension is owner of default voice per language logic.
* [T243579](https://phabricator.wikimedia.org/T243579) Add a pre-loaded trigger for Wikispeech.
* [T199414](https://phabricator.wikimedia.org/T199414) Config variable `WikispeechServerUrl` renamed `WikispeechSpeechoidUrl`.
* [T247395](https://phabricator.wikimedia.org/T247395) Limit input length in Speechoid requests.
* [T248825](https://phabricator.wikimedia.org/T248825) Clean up segmenting.
* [T248469](https://phabricator.wikimedia.org/T248469) Create database for utterance data.
* [T181780](https://phabricator.wikimedia.org/T181780) Use OOUI for the player controls.

### 0.1.4
2020-05-19
Speechoid: 0.1.0

* [T248472](https://phabricator.wikimedia.org/T248472) Create segment hasher.
* [T246079](https://phabricator.wikimedia.org/T246079) Add cache to segmenter.
* [T249198](https://phabricator.wikimedia.org/T249198) Version changelog file introduced.

### 0.1.3
2018-09-12
Speechoid: 0.1.0

* Version 0.1.3 and earlier is not documented in this file.

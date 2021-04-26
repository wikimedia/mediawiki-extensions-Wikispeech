<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Lexicon\ConfiguredLexiconStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconHandler;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWanCacheStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWikiStorage;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\WikispeechServices;

/** @phpcs-require-sorted-array */
return [
	'Wikispeech.ConfiguredLexiconStorage' => function ( MediaWikiServices $services ) : ConfiguredLexiconStorage {
		return new ConfiguredLexiconStorage(
			$services->getConfigFactory()
				->makeConfig( 'wikispeech' )
				->get( 'WikispeechPronunciationLexiconConfiguration' ),
			$services
		);
	},
	'Wikispeech.LexiconHandler' => function ( MediaWikiServices $services ) : LexiconHandler {
		return new LexiconHandler(
			WikispeechServices::getLexiconSpeechoidStorage(),
			WikispeechServices::getLexiconWanCacheStorage()
		);
	},
	'Wikispeech.LexiconSpeechoidStorage' => function ( MediaWikiServices $services ) : LexiconSpeechoidStorage {
		return new LexiconSpeechoidStorage(
			WikispeechServices::getSpeechoidConnector(),
			$services->getMainWANObjectCache()
		);
	},
	'Wikispeech.LexiconWanCacheStorage' => function ( MediaWikiServices $services ) : LexiconWanCacheStorage {
		return new LexiconWanCacheStorage(
			$services->getMainWANObjectCache()
		);
	},
	'Wikispeech.LexiconWikiStorage' => function ( MediaWikiServices $services ) : LexiconWikiStorage {
		return new LexiconWikiStorage(
			RequestContext::getMain()->getUser()
		);
	},
	'Wikispeech.SpeechoidConnector' => function ( MediaWikiServices $services ) : SpeechoidConnector {
		return new SpeechoidConnector(
			$services->getMainConfig(),
			$services->getHttpRequestFactory()
		);
	}
];

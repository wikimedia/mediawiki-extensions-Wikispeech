<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Lexicon\LexiconHandler;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWanCacheStorage;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\WikispeechServices;

/** @phpcs-require-sorted-array */
return [
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
	'Wikispeech.SpeechoidConnector' => function ( MediaWikiServices $services ) : SpeechoidConnector {
		return new SpeechoidConnector(
			$services->getMainConfig(),
			$services->getHttpRequestFactory()
		);
	}
];

<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Lexicon\ConfiguredLexiconStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconHandler;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWanCacheStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWikiStorage;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\Utterance\UtteranceGenerator;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWiki\Wikispeech\VoiceHandler;
use MediaWiki\Wikispeech\WikispeechServices;

/** @phpcs-require-sorted-array */
return [
	'Wikispeech.ConfiguredLexiconStorage' => static function (
		MediaWikiServices $services
	): ConfiguredLexiconStorage {
		return new ConfiguredLexiconStorage(
			$services->getConfigFactory()
				->makeConfig( 'wikispeech' )
				->get( 'WikispeechPronunciationLexiconConfiguration' ),
			$services
		);
	},
	'Wikispeech.LexiconHandler' => static function ( MediaWikiServices $services ): LexiconHandler {
		return new LexiconHandler(
			WikispeechServices::getLexiconSpeechoidStorage(),
			WikispeechServices::getLexiconWanCacheStorage()
		);
	},
	'Wikispeech.LexiconSpeechoidStorage' => static function ( MediaWikiServices $services ): LexiconSpeechoidStorage {
		return new LexiconSpeechoidStorage(
			WikispeechServices::getSpeechoidConnector(),
			$services->getMainWANObjectCache()
		);
	},
	'Wikispeech.LexiconWanCacheStorage' => static function ( MediaWikiServices $services ): LexiconWanCacheStorage {
		return new LexiconWanCacheStorage(
			$services->getMainWANObjectCache()
		);
	},
	'Wikispeech.LexiconWikiStorage' => static function ( MediaWikiServices $services ): LexiconWikiStorage {
		return new LexiconWikiStorage(
			RequestContext::getMain()->getUser()
		);
	},
	'Wikispeech.SegmentPageFactory' => static function ( MediaWikiServices $services ): SegmentPageFactory {
		return new SegmentPageFactory(
			$services->getMainWANObjectCache(),
			$services->getMainConfig(),
			$services->getRevisionStore(),
			$services->getHttpRequestFactory()
		);
	},
	'Wikispeech.SpeechoidConnector' => static function ( MediaWikiServices $services ): SpeechoidConnector {
		return new SpeechoidConnector(
			$services->getMainConfig(),
			$services->getHttpRequestFactory()
		);
	},
	'Wikispeech.UtteranceGenerator' => static function ( MediaWikiServices $services ): UtteranceGenerator {
		return new UtteranceGenerator(
			$services->get( 'Wikispeech.SpeechoidConnector' ),
			$services->get( 'Wikispeech.UtteranceStore' ),
			$services->get( 'Wikispeech.SegmentPageFactory' )
		);
	},
	'Wikispeech.UtteranceStore' => static function ( MediaWikiServices $services ): UtteranceStore {
		return new UtteranceStore();
	},
	'Wikispeech.VoiceHandler' => static function ( MediaWikiServices $services ): VoiceHandler {
		return new VoiceHandler(
		LoggerFactory::getInstance( 'Wikispeech' ),
		$services->getMainConfig(),
		$services->get( 'Wikispeech.SpeechoidConnector' ),
		$services->getMainWANObjectCache()
		);
	}

];

<?php

namespace MediaWiki\Wikispeech;

use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Lexicon\ConfiguredLexiconStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconHandler;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWanCacheStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWikiStorage;
use MediaWiki\Wikispeech\Utterance\UtteranceGenerator;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use Psr\Container\ContainerInterface;

class WikispeechServices {

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return ConfiguredLexiconStorage
	 */
	public static function getConfiguredLexiconStorage(
		?ContainerInterface $services = null
	): ConfiguredLexiconStorage {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.ConfiguredLexiconStorage' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return LexiconHandler
	 */
	public static function getLexiconHandler(
		?ContainerInterface $services = null
	): LexiconHandler {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.LexiconHandler' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return LexiconWikiStorage
	 */
	public static function getLexiconWikiStorage(
		?ContainerInterface $services = null
	): LexiconWikiStorage {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.LexiconWikiStorage' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return LexiconSpeechoidStorage
	 */
	public static function getLexiconSpeechoidStorage(
		?ContainerInterface $services = null
	): LexiconSpeechoidStorage {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.LexiconSpeechoidStorage' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return LexiconWanCacheStorage
	 */
	public static function getLexiconWanCacheStorage(
		?ContainerInterface $services = null
	): LexiconWanCacheStorage {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.LexiconWanCacheStorage' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return SpeechoidConnector
	 */
	public static function getSpeechoidConnector(
		?ContainerInterface $services = null
	): SpeechoidConnector {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.SpeechoidConnector' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return UtteranceGenerator
	 */
	public static function getUtteranceGenerator(
		?ContainerInterface $services = null
	): UtteranceGenerator {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.UtteranceGenerator' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to
	 *  use. If null, global MediaWikiServices::getInstance() will be
	 *  used instead.
	 *
	 * @return UtteranceStore
	 */
	public static function getUtteranceStore(
		?ContainerInterface $services = null
	): UtteranceStore {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'Wikispeech.UtteranceStore' );
	}

}

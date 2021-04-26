<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\WikispeechServices;
use MWException;

/**
 * A decorated {@link LexiconStorage}
 * selected based on the configuration value WikispeechPronunciationLexiconConfiguration
 * @since 0.1.9
 */
class ConfiguredLexiconStorage implements LexiconStorage {

	/** @var LexiconStorage */
	private $decorated;

	/**
	 * @param string $enumValue
	 * @param MediaWikiServices $services
	 * @throws MWException If value of WikispeechPronunciationLexiconConfiguration is unsupported.
	 * @since 0.1.9
	 */
	public function __construct(
		string $enumValue,
		MediaWikiServices $services
	) {
		$enumValueLower = trim( mb_strtolower( $enumValue ) );
		if ( $enumValueLower === 'speechoid' ) {
			$this->decorated = WikispeechServices::getLexiconSpeechoidStorage( $services );
		} elseif ( $enumValueLower === 'wiki' ) {
			$this->decorated = WikispeechServices::getLexiconWikiStorage( $services );
		} elseif ( $enumValueLower === 'wiki+speechoid' ) {
			$this->decorated = new LexiconHandler(
				WikispeechServices::getLexiconSpeechoidStorage( $services ),
				WikispeechServices::getLexiconWikiStorage( $services )
			);
		} elseif ( $enumValueLower === 'cache' ) {
			$this->decorated = WikispeechServices::getLexiconWanCacheStorage( $services );
		} elseif ( $enumValueLower === 'cache+speechoid' ) {
			$this->decorated = new LexiconHandler(
				WikispeechServices::getLexiconSpeechoidStorage( $services ),
				WikispeechServices::getLexiconWanCacheStorage( $services )
			);
		} else {
			throw new MWException( "Unsupported value for WikispeechPronunciationLexiconConfiguration: $enumValue" );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getEntry(
		string $language,
		string $key
	): ?LexiconEntry {
		return $this->decorated->getEntry( $language, $key );
	}

	/**
	 * @inheritDoc
	 */
	public function createEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$this->decorated->createEntryItem( $language, $key, $item );
	}

	/**
	 * @inheritDoc
	 */
	public function updateEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$this->decorated->updateEntryItem( $language, $key, $item );
	}

	/**
	 * @inheritDoc
	 */
	public function deleteEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$this->decorated->deleteEntryItem( $language, $key, $item );
	}
}

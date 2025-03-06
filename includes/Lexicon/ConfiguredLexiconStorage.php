<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use InvalidArgumentException;
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
	 * @since 0.1.9
	 * @param string $enumValue
	 * @param MediaWikiServices $services
	 * @throws MWException If value of WikispeechPronunciationLexiconConfiguration is unsupported.
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
	 * Returns the local lexicon entry without considering any fallbacks.
	 *
	 * @since 0.1.11
	 *
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry|null
	 */
	public function getLocalEntry(
		string $language,
		string $key
	): ?LexiconEntry {
		return $this->decorated->getLocalEntry( $language, $key );
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

	/**
	 * Synchronizes an entry item to both storages if applicable.
	 * Only works if the decorated storage is a LexiconHandler.
	 *
	 * @since 0.1.11
	 *
	 * @param string $language
	 * @param string $key
	 * @param int $speechoidId
	 * @throws InvalidArgumentException
	 */
	public function syncEntryItem(
		string $language,
		string $key,
		int $speechoidId
	): void {
		if ( $this->decorated instanceof LexiconHandler ) {
			if ( !$speechoidId ) {
				throw new InvalidArgumentException(
					"Cannot sync item without Speechoid identity"
				);
			}
			$this->decorated->syncEntryItem( $language, $key, $speechoidId );
		} else {
			throw new InvalidArgumentException(
				"Decorated lexicon storage is not of type LexiconHandler."
			);
		}
	}
}

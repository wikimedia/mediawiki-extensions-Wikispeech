<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use FormatJson;
use InvalidArgumentException;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\SpeechoidConnectorException;
use MWException;
use WANObjectCache;

/**
 * @since 0.1.8
 */
class LexiconSpeechoidStorage implements LexiconStorage {

	/** @var string */
	public const CACHE_CLASS = 'Wikispeech.LexiconSpeechoidStorage.lexiconName';

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/**
	 * @var mixed Some sort of cache, used to keep track of lexicons per language.
	 * Needs to support makeKey(), get() and set().
	 * Normally this would be a WANObjectCache.
	 */
	private $cache;

	/**
	 * @param SpeechoidConnector $speechoidConnector
	 * @param mixed $cache
	 * @since 0.1.8
	 */
	public function __construct(
		SpeechoidConnector $speechoidConnector,
		$cache
	) {
		$this->speechoidConnector = $speechoidConnector;
		$this->cache = $cache;
	}

	/**
	 * @param string $language ISO 639 language code passed down to Speechoid
	 * @return string|null
	 * @throws InvalidArgumentException If $language is not 2 characters long.
	 * @since 0.1.8
	 */
	private function findLexiconNameByLanguage(
		string $language
	): ?string {
		$language = strtolower( $language );
		$cacheKey = $this->cache->makeKey( self::CACHE_CLASS, $language );
		$lexiconName = $this->cache->get( $cacheKey );
		if ( !$lexiconName ) {
			$lexiconName = $this->speechoidConnector->findLexiconByLanguage( $language );
			// @todo Consider, if null we'll request on each attempt.
			// @todo Rather we could store as 'NULL' or something and keep track of that.
			// @todo But perhaps it's nicer to allow for new languages without the hour delay?
			$this->cache->set(
				$cacheKey,
				$lexiconName,
				WANObjectCache::TTL_HOUR
			);
		}
		return $lexiconName;
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry|null
	 * @throws MWException If no lexicon is available for language.
	 * @throws SpeechoidConnectorException On unexpected response from Speechoid.
	 * @since 0.1.8
	 */
	public function getEntry(
		string $language,
		string $key
	): ?LexiconEntry {
		$lexiconName = $this->findLexiconNameByLanguage( $language );
		if ( $lexiconName === null ) {
			throw new MWException( "No lexicon available for language $language" );
		}
		$status = $this->speechoidConnector->lookupLexiconEntries( $lexiconName, [ $key ] );
		if ( !$status->isOK() ) {
			throw new SpeechoidConnectorException( "Unexpected response from Speechoid: $status" );
		}
		$deserializedItems = $status->getValue();
		if ( $deserializedItems === [] ) {
			// no such key in lexicon
			return null;
		}
		$items = [];
		foreach ( $deserializedItems as $deserializedItem ) {
			$item = new LexiconEntryItem();
			$item->setProperties( $deserializedItem );
			$items[] = $item;
		}

		$entry = new LexiconEntry();
		$entry->setLanguage( $language );
		$entry->setKey( $key );
		$entry->setItems( $items );
		return $entry;
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If $item->item is null.
	 *  If Speechoid identity is already set.
	 * @throws MWException If no lexicon is available for language.
	 *  If failed to encode lexicon entry item properties to JSON.
	 *  If unable to add lexicon entry to Speechoid.
	 *  If unable to retrieve the created lexicon entry item from Speechoid.
	 * @since 0.1.8
	 */
	public function createEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		if ( $item->getProperties() === null ) {
			// @todo Better sanity check, ensure that required values (IPA, etc) are set.
			throw new InvalidArgumentException( '$item->item must not be null.' );
		}
		if ( $item->getSpeechoidIdentity() ) {
			throw new InvalidArgumentException( 'Speechoid identity is already set.' );
		}
		$lexiconName = $this->findLexiconNameByLanguage( $language );
		if ( $lexiconName === null ) {
			throw new MWException( "No lexicon available for language $language" );
		}
		$json = FormatJson::encode( $item->getProperties() );
		if ( $json === false ) {
			throw new MWException( 'Failed to encode lexicon entry item properties to JSON.' );
		}
		$status = $this->speechoidConnector->addLexiconEntry( $lexiconName, $json );
		if ( !$status->isOK() ) {
			throw new MWException( "Failed to add lexicon entry: $status" );
		}
		// Speechoid returns the identity. We need the actual entry.
		// Thus we make a new request and find that entry.
		// @todo Implement this when done on server side. https://phabricator.wikimedia.org/T277852

		/** @var int $speechoidIdentity */
		$speechoidIdentity = $status->getValue();
		$speechoidEntry = $this->getEntry( $language, $key );
		if ( $speechoidEntry === null ) {
			throw new MWException( "Expected the created lexicon entry to exist." );
		}
		$speechoidEntryItem = $speechoidEntry->findItemBySpeechoidIdentity( $speechoidIdentity );
		if ( $speechoidEntryItem === null ) {
			throw new MWException( 'Expected the created lexicon entry item to exist.' );
		}
		$item->copyFrom( $speechoidEntryItem );
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If $item->item is null.
	 *  If Speechoid identity is not set.
	 * @throws MWException If no lexicon is available for language.
	 *  If failed to encode lexicon entry item properties to JSON.
	 * @since 0.1.8
	 */
	public function updateEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		if ( $item->getProperties() === null ) {
			// @todo Better sanity check, ensure that required values (IPA, etc) are set.
			throw new InvalidArgumentException( '$item->item must not be null.' );
		}
		$speechoidIdentity = $item->getSpeechoidIdentity();
		if ( $speechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity not set.' );
		}
		$lexiconName = $this->findLexiconNameByLanguage( $language );
		if ( $lexiconName === null ) {
			throw new MWException( "No lexicon available for language $language" );
		}
		$json = FormatJson::encode( $item->getProperties() );
		if ( $json === false ) {
			throw new MWException( 'Failed to encode lexicon entry item properties to JSON.' );
		}
		// @todo The lexicon name is embedded in $json here.
		// @todo We want to use our own data model and produce a speechoid object from that instead.
		$status = $this->speechoidConnector->updateLexiconEntry( $json );
		if ( !$status->isOK() ) {
			throw new MWException( "Failed to update lexicon entry item: $status" );
		}

		// SpeechoidConnector::updateLexiconEntry does not return dbRef,
		// So we need to request the entry again from Speechoid.
		// @todo Ask STTS to return complete result at update.
		$speechoidEntry = $this->getEntry( $language, $key );
		if ( $speechoidEntry === null ) {
			throw new MWException( "Expected the updated lexicon entry to exist." );
		}
		$speechoidEntryItem = $speechoidEntry->findItemBySpeechoidIdentity( $speechoidIdentity );
		if ( $speechoidEntryItem === null ) {
			throw new MWException( 'Expected the updated lexicon entry item to exist.' );
		}
		$item->copyFrom( $speechoidEntryItem );
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If $item->item is null.
	 *  If Speechoid identity is not set.
	 * @throws MWException If no lexicon is available for language.
	 *  If failed to delete the lexicon entry item.
	 * @since 0.1.8
	 */
	public function deleteEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		if ( $item->getProperties() === null ) {
			throw new InvalidArgumentException( '$item->item must not be null.' );
		}
		$itemSpeechoidIdentity = $item->getSpeechoidIdentity();
		if ( $itemSpeechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity not set.' );
		}
		$lexiconName = $this->findLexiconNameByLanguage( $language );
		if ( $lexiconName === null ) {
			throw new MWException( "No lexicon available for language $language" );
		}
		$status = $this->speechoidConnector->deleteLexiconEntry(
			$lexiconName,
			$itemSpeechoidIdentity
		);
		if ( !$status->isOK() ) {
			throw new MWException( "Failed to delete lexicon entry item: $status" );
		}
	}

}

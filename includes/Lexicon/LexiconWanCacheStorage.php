<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use InvalidArgumentException;
use MWException;
use WANObjectCache;

/**
 * A local lexicon storage implemented using {@link WANObjectCache}.
 * It is created as an initial test storage for use at development time only.
 *
 * @since 0.1.8
 */
class LexiconWanCacheStorage implements LexiconLocalStorage {

	/** @var string */
	private const CACHE_CLASS = 'Wikispeech.LexiconWanCacheStorage';

	/** @var WANObjectCache */
	private $wanObjectCache;

	/**
	 * @param WANObjectCache $wanObjectCache
	 * @since 0.1.8
	 */
	public function __construct( WANObjectCache $wanObjectCache ) {
		$this->wanObjectCache = $wanObjectCache;
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @return string
	 * @since 0.1.8
	 */
	private function cacheKeyFactory(
		string $language,
		string $key
	): string {
		return $this->wanObjectCache->makeKey( self::CACHE_CLASS, $language, $key );
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry|null
	 * @since 0.1.8
	 */
	public function getEntry(
		string $language,
		string $key
	): ?LexiconEntry {
		$entry = $this->wanObjectCache->get( $this->cacheKeyFactory( $language, $key ) );
		if ( $entry === false ) {
			return null;
		}
		return $entry;
	}

	/**
	 * @param LexiconEntry $entry
	 * @throws InvalidArgumentException If $entry->language or ->key is null.
	 * @since 0.1.8
	 */
	private function putEntry( LexiconEntry $entry ): void {
		$language = $entry->getLanguage();
		if ( $language === null ) {
			throw new InvalidArgumentException( '$entry->language must not be null.' );
		}
		$key = $entry->getKey();
		if ( $key === null ) {
			throw new InvalidArgumentException( '$entry->key must not be null.' );
		}
		$this->wanObjectCache->set(
			$this->cacheKeyFactory( $language, $key ),
			$entry,
			WANObjectCache::TTL_INDEFINITE
		);
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @return bool
	 * @throws InvalidArgumentException If $item has no Speechoid identity.
	 * @since 0.1.8
	 */
	public function entryItemExists(
		string $language,
		string $key,
		LexiconEntryItem $item
	): bool {
		$itemSpeechoidIdentity = $item->getSpeechoidIdentity();
		if ( $itemSpeechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity is missing.' );
		}
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			return false;
		}
		return $entry->findItemBySpeechoidIdentity( $itemSpeechoidIdentity ) !== null;
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If $item->properties is null.
	 *  If Speechoid identity is not set.
	 * @since 0.1.8
	 */
	public function createEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		if ( $item->getProperties() === null ) {
			// @todo Better sanity check, ensure that required values (IPA, etc) are set.
			throw new InvalidArgumentException( '$item->properties must not be null.' );
		}
		$itemSpeechoidIdentity = $item->getSpeechoidIdentity();
		if ( $itemSpeechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity not set' );
		}
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			$entry = new LexiconEntry();
			$entry->setKey( $key );
			$entry->setLanguage( $language );
			$entry->setItems( [ $item ] );
		} else {
			if ( $entry->findItemBySpeechoidIdentity( $itemSpeechoidIdentity ) !== null ) {
				throw new MWException( 'Attempting to create an entry item that already exists.' );
			}
			$entry->addItem( $item );
		}
		$this->putEntry( $entry );
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If $item->item is null.
	 *  If Speechoid identity is not set.
	 * @throws MWException If attempting to update a non existing entry or entry item.
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
		$itemSpeechoidIdentity = $item->getSpeechoidIdentity();
		if ( $itemSpeechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity not set.' );
		}
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			throw new MWException( 'Attempting to update a non existing entry.' );
		}
		$entry->replaceItem( $item );
		$this->putEntry( $entry );
	}

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If $item->item is null.
	 *  If Speechoid identity is not set.
	 * @throws MWException If attempting to delete a non existing entry or item.
	 * @since 0.1.8
	 */
	public function deleteEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		if ( $item->getProperties() === null ) {
			// @todo Better sanity check, ensure that required values (IPA, etc) are set.
			throw new InvalidArgumentException( '$item->item must not be null.' );
		}
		$itemSpeechoidIdentity = $item->getSpeechoidIdentity();
		if ( $itemSpeechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity not set.' );
		}
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			throw new MWException( 'Attempting to delete a non existing entry.' );
		}
		$entry->deleteItem( $item );
		$this->putEntry( $entry );
	}

}

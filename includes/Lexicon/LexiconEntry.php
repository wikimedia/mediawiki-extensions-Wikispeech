<?php

namespace MediaWiki\Wikispeech\Lexicon;

use InvalidArgumentException;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * An entry in the pronunciation lexicon.
 *
 * An orthographic representation of word (key) in a given language,
 * associated with a number of meanings that may have different pronunciations (items).
 *
 * @since 0.1.8
 */
class LexiconEntry {

	/** @var string|null */
	private $language;

	/** @var string|null */
	private $key;

	/** @var LexiconEntryItem[] Lexicon entries sharing the key */
	private $items = [];

	// helper functions

	/**
	 * @param string $speechoidIdentity
	 * @return int|null Index of first item that match $speechoidIdentity
	 * @since 0.1.8
	 */
	public function findItemIndexBySpeechoidIdentity( string $speechoidIdentity ): ?int {
		foreach ( $this->items as $itemIndex => $item ) {
			if ( $speechoidIdentity === $item->getSpeechoidIdentity() ) {
				return $itemIndex;
			}
		}
		return null;
	}

	/**
	 * @param string $speechoidIdentity
	 * @return LexiconEntryItem|null First item that match $speechoidIdentity
	 * @since 0.1.8
	 */
	public function findItemBySpeechoidIdentity( string $speechoidIdentity ): ?LexiconEntryItem {
		$index = $this->findItemIndexBySpeechoidIdentity( $speechoidIdentity );
		return $index === null ? null : $this->items[$index];
	}

	/**
	 * @param int $index
	 * @return LexiconEntryItem
	 * @since 0.1.9
	 */
	public function getItemAt( int $index ) {
		return $this->items[$index];
	}

	/**
	 * @param LexiconEntryItem $item
	 * @since 0.1.9
	 */
	public function addItem( LexiconEntryItem $item ) {
		$this->items[] = $item;
	}

	/**
	 * Replaces the item in the list of items that match Speechoid identity of the given item.
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If no item in the list match the identity of the given item.
	 *  If item is missing speechoid identity.
	 * @since 0.1.9
	 */
	public function replaceItem( LexiconEntryItem $item ) {
		$speechoidIdentity = $item->getSpeechoidIdentity();
		if ( $speechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity not set.' );
		}
		$index = $this->findItemIndexBySpeechoidIdentity( $speechoidIdentity );
		if ( $index === null ) {
			throw new InvalidArgumentException( 'Item is not a member' );
		}
		$this->replaceItemAt( $index, $item );
	}

	/**
	 * @param int $index
	 * @param LexiconEntryItem $item
	 * @since 0.1.9
	 */
	public function replaceItemAt( int $index, LexiconEntryItem $item ) {
		$this->items[$index] = $item;
	}

	/**
	 * Removes the item in the list of items that match Speechoid identity of the given item.
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If no item in the list match the identity of the given item.
	 *  If speechoid identity is not set in item.
	 * @since 0.1.9
	 */
	public function deleteItem( LexiconEntryItem $item ) {
		$speechoidIdentity = $item->getSpeechoidIdentity();
		if ( $speechoidIdentity === null ) {
			throw new InvalidArgumentException( 'Speechoid identity not set.' );
		}
		$index = $this->findItemIndexBySpeechoidIdentity( $speechoidIdentity );
		if ( $index === null ) {
			throw new InvalidArgumentException( 'Item is not a member' );
		}
		$this->deleteItemAt( $index );
	}

	/**
	 * @param int $index
	 * @since 0.1.9
	 */
	public function deleteItemAt( int $index ) {
		unset( $this->items[$index] );
	}

	// getters and setters

	/**
	 * @return string|null
	 * @since 0.1.8
	 */
	public function getLanguage(): ?string {
		return $this->language;
	}

	/**
	 * @param string $language
	 * @since 0.1.8
	 */
	public function setLanguage( string $language ): void {
		$this->language = $language;
	}

	/**
	 * @return string|null
	 * @since 0.1.8
	 */
	public function getKey(): ?string {
		return $this->key;
	}

	/**
	 * @param string $key
	 * @since 0.1.8
	 */
	public function setKey( string $key ): void {
		$this->key = $key;
	}

	/**
	 * Do not use this method to modify the array from other classes than this!
	 * If you do, you will be modifying a clone of the array.
	 *
	 * @see deleteItemAt
	 * @see deleteItem
	 * @see replaceItemAt
	 * @see replaceItem
	 * @see addItem
	 * @see getItemAt
	 * @return LexiconEntryItem[]
	 * @since 0.1.8
	 */
	public function getItems(): array {
		return $this->items;
	}

	/**
	 * @param LexiconEntryItem[] $items
	 * @since 0.1.8
	 */
	public function setItems( array $items ): void {
		$this->items = $items;
	}

}

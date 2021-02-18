<?php

namespace MediaWiki\Wikispeech\Lexicon;

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
		for ( $itemIndex = 0; $itemIndex < count( $this->getItems() ); $itemIndex++ ) {
			$item = $this->getItems()[$itemIndex];
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
		return $index === null ? null : $this->getItems()[$index];
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

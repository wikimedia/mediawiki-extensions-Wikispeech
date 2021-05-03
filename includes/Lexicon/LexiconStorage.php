<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ExternalStoreException;

/**
 * @since 0.1.8
 */
interface LexiconStorage {

	/**
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry|null
	 * @throws ExternalStoreException
	 */
	public function getEntry(
		string $language,
		string $key
	): ?LexiconEntry;

	/**
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item Will be updated on success.
	 */
	public function createEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void;

	/**
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item Will be updated on success.
	 */
	public function updateEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void;

	/**
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 */
	public function deleteEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void;
}

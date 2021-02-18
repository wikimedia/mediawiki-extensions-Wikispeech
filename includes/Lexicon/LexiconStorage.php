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
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry|null
	 * @throws ExternalStoreException
	 * @since 0.1.8
	 */
	public function getEntry(
		string $language,
		string $key
	): ?LexiconEntry;

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item Will be updated on success.
	 * @since 0.1.8
	 */
	public function createEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void;

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item Will be updated on success.
	 * @since 0.1.8
	 */
	public function updateEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void;

	/**
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @since 0.1.8
	 */
	public function deleteEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void;
}

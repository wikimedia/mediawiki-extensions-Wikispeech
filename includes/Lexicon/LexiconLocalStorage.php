<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.8
 */
interface LexiconLocalStorage extends LexiconStorage {

	/**
	 * Whether or not a {@link LexiconEntryItem} exists in the storage.
	 * This says nothing about the equality of the pronunciation of $item and the item in the storage,
	 * it only means that they are the same words with the same semantic meaning.
	 *
	 * @note Currently we are only able to match semantics by comparing the Speechoid identity
	 *  between items. This is not a solution for the long run as identities might change as
	 *  Speechoid is reinstalled or updated. Future implementations will have to contain a way
	 *  to evaluate equality between items by actually inspecting the semantics.
	 *
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @return bool
	 * @since 0.1.8
	 */
	public function entryItemExists(
		string $language,
		string $key,
		LexiconEntryItem $item
	): bool;

}

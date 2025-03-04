<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use FormatJson;
use InvalidArgumentException;
use MWException;

/**
 * Keeps track of lexicon entries in Speechoid and local storage.
 *
 * The local storage could contain less items per entry than the Speechoid storage.
 * The local storage must not contain items that is not available in the Speechoid storage.
 * Items in the local storage must equal those in the Speechoid storage.
 *
 * All requests are first sent to Speechoid, then to the local. If Speechoid fails,
 * nothing will be invoked on the local. However, since all actions are atomic, it
 * is possible we mess things up if one of the systems shut down in the middle of
 * everything. We might for instance access Speechoid to retrieve the current version
 * of an item prior to updating it locally or in Speechoid. Such an event could put
 * us out of sync for this item.
 *
 * The only way to solve this problem is to introduce a transaction that spans
 * both storages, so that we can roll back changes in both of them, or making sure
 * that data isn't committed if an operation is killed halfway through.
 * That would be quite an undertaking and we doubt this will ever be implemented.
 *
 * @note What if Speechoid is successful but the local fails?
 * If it's due to locking of local Wiki page, then user will get an error and will
 * have to retry.
 *
 * @since 0.1.8
 */
class LexiconHandler implements LexiconStorage {

	/** @var LexiconLocalStorage */
	private $localStorage;

	/** @var LexiconSpeechoidStorage */
	private $speechoidStorage;

	/**
	 * @since 0.1.8
	 * @param LexiconSpeechoidStorage $speechoidStorage
	 * @param LexiconStorage $localStorage
	 */
	public function __construct(
		LexiconSpeechoidStorage $speechoidStorage,
		LexiconStorage $localStorage
	) {
		$this->speechoidStorage = $speechoidStorage;
		$this->localStorage = $localStorage;
	}

	/**
	 * Retrieves entries from both local and Speechoid lexicon
	 * and ensures data integrity before returning the Speechoid entry.
	 *
	 * Entry items existing in local and not in Speechoid is an error.
	 * Entry items sharing identity that otherwise does not equal each other is an error.
	 *
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry|null Entry retrieved from Speechoid
	 * @throws MWException On various merge errors.
	 */
	public function getEntry(
		string $language,
		string $key
	): ?LexiconEntry {
		$speechoidEntry = $this->speechoidStorage->getEntry( $language, $key );
		$localEntry = $this->localStorage->getEntry( $language, $key );
		if ( $localEntry === null && $speechoidEntry !== null ) {
			return $speechoidEntry;
		} elseif ( $localEntry !== null && $speechoidEntry === null ) {
			throw new MWException(
				'Storages out of sync. Local storage contains items unknown to Speechoid.'
			);
		} elseif ( $localEntry === null && $speechoidEntry === null ) {
			return null;
		}

		// Ensure that all items in local entry also exists in Speechoid entry.

		/** @var LexiconEntryItem[] $itemsOutOfSync */
		$itemsOutOfSync = [];

		foreach ( $localEntry->getItems() as $localEntryItem ) {
			$localEntryItemSpeechoidIdentity = $localEntryItem->getSpeechoidIdentity();
			if ( $localEntryItemSpeechoidIdentity === null ) {
				$itemsOutOfSync[] = $this->outOfSyncItemFactory(
					'Local missing identity',
					$localEntryItem,
					null
				);
				continue;
			}

			$matchingSpeechoidEntryItem = null;
			foreach ( $speechoidEntry->getItems() as $speechoidEntryItem ) {
				if ( $speechoidEntryItem->getSpeechoidIdentity() === $localEntryItemSpeechoidIdentity ) {
					$matchingSpeechoidEntryItem = $speechoidEntryItem;
					break;
				}
			}

			// @note Validation is split up to show we want future handling that differs depending
			// on what error we have. It might in fact be Speechoid that is out of sync.

			if ( $matchingSpeechoidEntryItem === null ) {
				// Only exists in local lexicon.
				$itemsOutOfSync[] = $this->outOfSyncItemFactory(
					'Identity only exists locally',
					$localEntryItem,
					null
				);
				continue;
			}

			// Use != instead of !== since the latter also compares order, which is not relevant.
			if ( $localEntryItem->getProperties() != $matchingSpeechoidEntryItem->getProperties() ) {
				// Exists in both, but the item properties are not equal.
				$itemsOutOfSync[] = $this->outOfSyncItemFactory(
					'Same identities but not equal',
					$localEntryItem,
					$matchingSpeechoidEntryItem
				);
				continue;
			}
		}

		$failedLocalEntryItemsCount = count( $itemsOutOfSync );
		if ( $failedLocalEntryItemsCount > 0 ) {
			throw new MWException(
				'Storages out of sync. ' . $failedLocalEntryItemsCount .
				' entry items from local and Speechoid lexicon failed to merge.: ' .
				FormatJson::encode( $itemsOutOfSync )
			);
		}

		return $speechoidEntry;
	}

	/**
	 * @since 0.1.9
	 * @param string $message
	 * @param LexiconEntryItem|null $localItem
	 * @param LexiconEntryItem|null $speechoidItem
	 * @return array
	 */
	private function outOfSyncItemFactory(
		string $message,
		?LexiconEntryItem $localItem,
		?LexiconEntryItem $speechoidItem
	): array {
		return [
			'message' => $message,
			'localItem' => $localItem !== null ? $localItem->getProperties() : null,
			'speechoidItem' => $speechoidItem !== null ? $speechoidItem->getProperties() : null
		];
	}

	/**
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 */
	public function createEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$this->updateEntryItem( $language, $key, $item );
	}

	/**
	 * This is in fact a put-action, it will call underlying create methods if required.
	 *
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item Will be updated on success.
	 * @throws InvalidArgumentException If $item->properties is null.
	 * @throws MWException If unable to push to any storage.
	 *  If successfully pushed to Speechoid but unable to push to local storage.
	 */
	public function updateEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		if ( $item->getProperties() === null ) {
			// @todo Better sanity check, ensure that required values (IPA, etc) are set.
			throw new InvalidArgumentException( '$item->properties must not be null.' );
		}
		$itemSpeechoidIdentity = $item->getSpeechoidIdentity();
		$wasPreferred = false;

		// will check if the entry already exists in speechoid storage and has the same properties
		// if the item exists with the same ID, then compare to decide if it should be updated or not
		if ( $itemSpeechoidIdentity !== null ) {
			$currentEntry = $this->speechoidStorage->getEntry( $language, $key );
			if ( $currentEntry !== null ) {
				$currentItem = $currentEntry->findItemBySpeechoidIdentity( $itemSpeechoidIdentity );
				if ( $currentItem !== null && $currentItem->getProperties() == $item->getProperties() ) {
					throw new NullEditLexiconException();
				}
				$wasPreferred = $currentItem ? $currentItem->getPreferred() : false;
			}
		}

		if ( $itemSpeechoidIdentity === null ) {
			$this->speechoidStorage->createEntryItem( $language, $key, $item );
			$this->localStorage->createEntryItem( $language, $key, $item );
		} else {
			// If item has not been created in local storage,
			// then we should fetch the current revision from Speechoid
			// and create that in local storage
			// before we then update the local storage with the new data.
			if ( !$this->localStorage->entryItemExists( $language, $key, $item ) ) {
				$currentSpeechoidEntry = $this->speechoidStorage->getEntry( $language, $key );
				if ( $currentSpeechoidEntry === null ) {
					throw new MWException( 'Expected current Speechoid entry to exist.' );
				}
				$currentSpeechoidEntryItem = $currentSpeechoidEntry->findItemBySpeechoidIdentity(
					$itemSpeechoidIdentity
				);
				if ( $currentSpeechoidEntryItem === null ) {
					throw new MWException( 'Expected current Speechoid entry item to exists.' );
				}
				$wasPreferred = $currentSpeechoidEntryItem->getPreferred();
				$this->localStorage->createEntryItem( $language, $key, $currentSpeechoidEntryItem );
			} else {
				$currentLocalEntry = $this->localStorage->
					getEntry( $language, $key );
				$currentLocalEntryItem = $currentLocalEntry->
					findItemBySpeechoidIdentity( $itemSpeechoidIdentity );
				$wasPreferred = $currentLocalEntryItem->getPreferred();
			}
			$this->speechoidStorage->updateEntryItem( $language, $key, $item );
			$this->localStorage->updateEntryItem( $language, $key, $item );
		}
		if ( $item->getPreferred() && !$wasPreferred ) {
			$this->removePreferred( $language, $key, $item );
		}
	}

	/**
	 * Remove "preferred" from all item in the an entry except one
	 *
	 * This is used to mirror the behaviour of Speechoid, which does
	 * this internally when preferred is set to true.
	 *
	 * @since 0.1.10
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $excludedItem This item will not be
	 *  touched. Used to keep the preferred on the item that was just
	 *  set to true.
	 */
	private function removePreferred( $language, $key, $excludedItem ): void {
		$entry = $this->localStorage->getEntry( $language, $key );
		foreach ( $entry->getItems() as $item ) {
			if ( $item->getSpeechoidIdentity() !== $excludedItem->getSpeechoidIdentity() ) {
				$item->removePreferred();
				$this->localStorage->updateEntryItem( $language, $key, $item );
			}
		}
	}

	/**
	 * @since 0.1.8
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws MWException If successfully deleted in Speechoid but unable to delete in local storage.
	 */
	public function deleteEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$this->speechoidStorage->deleteEntryItem( $language, $key, $item );
		$this->localStorage->deleteEntryItem( $language, $key, $item );
	}
}

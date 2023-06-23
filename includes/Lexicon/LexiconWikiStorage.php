<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use CommentStoreComment;
use Content;
use ExternalStoreException;
use FormatJson;
use InvalidArgumentException;
use JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MWException;
use Title;
use User;
use WikiPage;

/**
 * Keeps track of pronunciation lexicon entries as JSON content in the main slot
 * of a wiki page named [[Pronunciation_lexicon:language/key]].
 *
 * @todo
 * It is possible for users to edit the lexicon entries using the normal wiki interface.
 * As for now, there is no way for the extension to populate such changes to Speechoid.
 * In the future, we might consider adding a hook that determine how the change was done
 * in order to either stop the edit or to pass it down to Speechoid.
 *
 * @since 0.1.9
 */
class LexiconWikiStorage implements LexiconLocalStorage {

	/** @var User */
	private $user;

	/**
	 * @since 0.1.9
	 * @param User $user
	 */
	public function __construct( User $user ) {
		$this->user = $user;
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @return Title
	 */
	private function lexiconEntryTitleFactory(
		string $language,
		string $key
	): Title {
		// @todo Switch to TitleFactory when upgrading to MW 1.36+
		return Title::makeTitle( NS_PRONUNCIATION_LEXICON, $language )->getSubpage( $key );
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @return WikiPage
	 */
	private function lexiconEntryWikiPageFactory(
		string $language,
		string $key
	): WikiPage {
		return MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( $this->lexiconEntryTitleFactory( $language, $key ) );
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @return bool
	 */
	public function entryItemExists(
		string $language,
		string $key,
		LexiconEntryItem $item
	): bool {
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			return false;
		}
		return $entry->findItemByItem( $item ) !== null;
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry|null
	 */
	public function getEntry(
		string $language,
		string $key
	): ?LexiconEntry {
		if ( !$language || !$key ) {
			return null;
		}

		$wikiPage = $this->lexiconEntryWikiPageFactory( $language, $key );
		if ( !$wikiPage->exists() ) {
			return null;
		}
		/** @var JsonContent $content */
		$content = $wikiPage->getContent( RevisionRecord::FOR_PUBLIC );
		return self::deserializeEntryContent( $content, $language, $key );
	}

	/**
	 * This is a public static method because we might want to access this from a hook
	 * that validate manual changes to lexicon wiki pages. In fact, this is how it first
	 * was implemented, but we reverted. Kept the method like this though.
	 * As a reminder if nothing else.
	 *
	 * @since 0.1.9
	 * @param Content $content
	 * @param string $language
	 * @param string $key
	 * @return LexiconEntry
	 * @throws ExternalStoreException If revision content is not of type JSON.
	 *   If revision content failed to be deserialized as JSON.
	 */
	public static function deserializeEntryContent(
		Content $content,
		string $language,
		string $key
	) {
		if ( !( $content instanceof JsonContent ) ) {
			throw new ExternalStoreException( 'Revision content is not of type JsonContent' );
		}
		$entry = new LexiconEntry();
		$entry->setLanguage( $language );
		$entry->setKey( $key );
		// $content->getData() does not force associative array.
		$status = FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC );
		if ( !$status->isOK() ) {
			throw new ExternalStoreException( 'Failed to decode revision content as JSON' );
		}
		$deserialized = $status->getValue();
		foreach ( $deserialized as $itemProperties ) {
			$entryItem = new LexiconEntryItem();
			$entryItem->setProperties( $itemProperties );
			// @todo assert language and key match parameters
			$entry->addItem( $entryItem );
		}
		return $entry;
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If the entry already contains an item with the same id.
	 */
	public function createEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			$entry = new LexiconEntry();
			$entry->setLanguage( $language );
			$entry->setKey( $key );
		} else {
			if ( $entry->findItemByItem( $item ) !== null ) {
				throw new InvalidArgumentException(
					'Lexicon entry already contains an item with the same Speechoid identity.'
				);
			}
		}
		$entry->addItem( $item );
		$this->saveLexiconEntryRevision(
			$language,
			$key,
			$entry,
			'Via LexiconWikiStorage::createEntryItem'
		);
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If the entry with language and key does not exist.
	 *  If entry already contains an item with the same id.
	 */
	public function updateEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			throw new InvalidArgumentException(
				'Lexicon entry does not exist for that language and key.'
			);
		}
		$itemIndex = $entry->findItemIndexByItem( $item );
		if ( $itemIndex === null ) {
			throw new InvalidArgumentException(
				'Lexicon entry does not contain an item with the same Speechoid identity.'
			);
		}
		$entry->replaceItemAt( $itemIndex, $item );
		$this->saveLexiconEntryRevision(
			$language,
			$key,
			$entry,
			'Via LexiconWikiStorage::updateEntryItem'
		);
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntryItem $item
	 * @throws InvalidArgumentException If the entry with language and key does not exist.
	 *  If entry already contains an item with the same id.
	 */
	public function deleteEntryItem(
		string $language,
		string $key,
		LexiconEntryItem $item
	): void {
		$entry = $this->getEntry( $language, $key );
		if ( $entry === null ) {
			throw new InvalidArgumentException(
				'Lexicon entry does not exist for that language and key.'
			);
		}
		$itemIndex = $entry->findItemIndexByItem( $item );
		if ( $itemIndex === null ) {
			throw new InvalidArgumentException(
				'Lexicon entry does not contain an item with the same Speechoid identity.'
			);
		}
		$entry->deleteItemAt( $itemIndex );
		$this->saveLexiconEntryRevision(
			$language,
			$key,
			$entry,
			'Via LexiconWikiStorage::deleteEntryItem'
		);
	}

	/**
	 * @since 0.1.9
	 * @param string $language
	 * @param string $key
	 * @param LexiconEntry $entry
	 * @param string $revisionComment
	 * @throws MWException If failed to encode entry to JSON.
	 */
	private function saveLexiconEntryRevision(
		string $language,
		string $key,
		LexiconEntry $entry,
		string $revisionComment
	) {
		$array = [];
		foreach ( $entry->getItems() as $entryItem ) {
			$array[] = $entryItem->getProperties();
		}
		$json = FormatJson::encode( $array );
		if ( $json === false ) {
			throw new MWException( 'Failed to encode entry to JSON.' );
		}
		$content = new JsonContent( $json );
		$wikiPage = $this->lexiconEntryWikiPageFactory( $language, $key );
		$pageUpdater = $wikiPage->newPageUpdater( $this->user );
		$pageUpdater->setContent( SlotRecord::MAIN, $content );
		$pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( $revisionComment )
		);
	}
}

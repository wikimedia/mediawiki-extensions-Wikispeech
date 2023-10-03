<?php

namespace MediaWiki\Wikispeech\Tests\Itegration\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Lexicon\LexiconEntry;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconWikiStorage;
use MediaWikiIntegrationTestCase;
use MWException;
use Title;

/**
 * @since 0.1.9
 * @group Database
 * @covers \MediaWiki\Wikispeech\Lexicon\LexiconWikiStorage
 */
class LexiconWikiStorageTest extends MediaWikiIntegrationTestCase {

	/** @var LexiconWikiStorage */
	private $storage;

	/** @var LexiconEntry[] */
	private $entriesTouched;

	protected function setUp(): void {
		parent::setUp();
		$this->storage = new LexiconWikiStorage( $this->getTestUser()->getUser() );
		$this->entriesTouched = [];
	}

	protected function tearDown(): void {
		$user = $this->getTestSysop()->getUser();
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		foreach ( $this->entriesTouched as $entry ) {
			try {
				$entrySubpage = Title::newFromText(
						$entry->getLanguage(), NS_PRONUNCIATION_LEXICON
					)->getSubpage( $entry->getKey() );
				$p = $wikiPageFactory->newFromTitle( $entrySubpage );
				if ( $p->exists() ) {
					$p->doDeleteArticleReal( "testing done.", $user );
				}
			} catch ( MWException $ex ) {
				// fail silently
			}
		}
		parent::tearDown();
	}

	/**
	 * @param string $language Needed to flush out any wiki page we created at teardown.
	 * @param string $key E.g. "tomten"
	 * @param int $identity
	 * @return LexiconEntryItem
	 */
	private function entryItemFactory(
		string $language,
		string $key,
		int $identity
	): LexiconEntryItem {
		$properties = [
			"id" => $identity,
			"lexRef" => [
				"dbRef" => "sv_se_nst_lex",
				"lexName" => "sv-se.nst"
			],
			"strn" => $key,
			"language" => "sv-se",
		];
		$entryItem = new LexiconEntryItem();
		$entryItem->setProperties( $properties );
		$entry = new LexiconEntry();
		$entry->setKey( $key );
		$entry->setLanguage( $language );
		$this->entriesTouched[] = $entry;
		return $entryItem;
	}

	public function testEntryItemExists() {
		$item1 = $this->entryItemFactory( 'sv', 'a', 1 );
		$item2 = $this->entryItemFactory( 'sv', 'a', 2 );

		$this->assertFalse( $this->storage->entryItemExists( 'sv', 'a', $item1 ) );
		$this->assertFalse( $this->storage->entryItemExists( 'sv', 'a', $item2 ) );

		$this->storage->createEntryItem(
			'sv',
			'a',
			$item2
		);
		$this->assertFalse( $this->storage->entryItemExists( 'sv', 'a', $item1 ) );
		$this->assertTrue( $this->storage->entryItemExists( 'sv', 'a', $item2 ) );

		$this->storage->createEntryItem(
			'sv',
			'a',
			$item1
		);
		$this->assertTrue( $this->storage->entryItemExists( 'sv', 'a', $item1 ) );
		$this->assertTrue( $this->storage->entryItemExists( 'sv', 'a', $item2 ) );

		$this->storage->deleteEntryItem( 'sv', 'a', $item1 );
		$this->assertFalse( $this->storage->entryItemExists( 'sv', 'a', $item1 ) );
		$this->assertTrue( $this->storage->entryItemExists( 'sv', 'a', $item2 ) );

		$this->storage->deleteEntryItem( 'sv', 'a', $item2 );
		$this->assertFalse( $this->storage->entryItemExists( 'sv', 'a', $item1 ) );
		$this->assertFalse( $this->storage->entryItemExists( 'sv', 'a', $item2 ) );
	}

	public function testGetEntry() {
		$item = $this->entryItemFactory( 'sv', 'b', 3 );
		$this->storage->createEntryItem(
			'sv',
			'b',
			$item
		);
		$entry = $this->storage->getEntry( 'sv', 'b' );
		$this->assertSame( 'sv', $entry->getLanguage() );
		$this->assertSame( 'b', $entry->getKey() );
		$this->assertCount( 1, $entry->getItems() );
	}

	public function testCreateEntryItem_addMultipleEntries() {
		$item1 = $this->entryItemFactory( 'sv', 'c', 4 );
		$this->storage->createEntryItem(
			'sv',
			'c',
			$item1
		);
		$item2 = $this->entryItemFactory( 'sv', 'c', 5 );
		$this->storage->createEntryItem(
			'sv',
			'c',
			$item2
		);
		$entry = $this->storage->getEntry( 'sv', 'c' );
		$this->assertSame( 'sv', $entry->getLanguage() );
		$this->assertSame( 'c', $entry->getKey() );
		$this->assertCount( 2, $entry->getItems() );
	}

	public function testDeleteEntryItem() {
		$item1 = $this->entryItemFactory( 'sv', 'd', 6 );
		$this->storage->createEntryItem(
			'sv',
			'd',
			$item1
		);
		$item2 = $this->entryItemFactory( 'sv', 'd', 7 );
		$this->storage->createEntryItem(
			'sv',
			'd',
			$item2
		);
		$entry = $this->storage->getEntry( 'sv', 'd' );
		$this->assertCount( 2, $entry->getItems() );
		$this->assertSame( 6, $entry->getItemAt( 0 )->getSpeechoidIdentity() );
		$this->assertSame( 7, $entry->getItemAt( 1 )->getSpeechoidIdentity() );
		$this->storage->deleteEntryItem( 'sv', 'd', $item1 );
		$entry = $this->storage->getEntry( 'sv', 'd' );
		$this->assertCount( 1, $entry->getItems() );
		$this->assertSame( 7, $entry->getItemAt( 0 )->getSpeechoidIdentity() );
		$this->storage->deleteEntryItem( 'sv', 'd', $item2 );
		$entry = $this->storage->getEntry( 'sv', 'd' );
		$this->assertCount( 0, $entry->getItems() );
	}

}

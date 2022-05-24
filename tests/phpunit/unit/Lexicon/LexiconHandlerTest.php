<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use HashBagOStuff;
use MediaWiki\Wikispeech\Lexicon\LexiconEntry;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconHandler;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWanCacheStorage;
use MediaWikiUnitTestCase;

/**
 * @since 0.1.8
 * @covers \MediaWiki\Wikispeech\Lexicon\LexiconHandler
 */
class LexiconHandlerTest extends MediaWikiUnitTestCase {

	/** @var LexiconEntry */
	private $mockedLexiconEntry;

	/** @var HashBagOStuff */
	private $cache;

	protected function setUp(): void {
		parent::setUp();

		$this->cache = new HashBagOStuff();
		$cacheKey = $this->cache->makeKey( LexiconSpeechoidStorage::CACHE_CLASS, 'sv' );
		$this->cache->set( $cacheKey, 'sv_se_nst_lex:sv-se.nst' );

		$this->mockedLexiconEntry = new LexiconEntry();
		$this->mockedLexiconEntry->setLanguage( 'sv' );
		$this->mockedLexiconEntry->setKey( 'tomten' );

		$mockedEntryItem0 = new LexiconEntryItem();
		$mockedEntryItem0->setProperties( [ 'id' => 0 ] );
		$this->mockedLexiconEntry->addItem( $mockedEntryItem0 );

		$mockedEntryItem1 = new LexiconEntryItem();
		$mockedEntryItem1->setProperties( [ 'id' => 1 ] );
		$this->mockedLexiconEntry->addItem( $mockedEntryItem1 );
	}

	public function testGetEntry_existingInBoth_retrieved() {
		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $this->mockedLexiconEntry );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$localMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $this->mockedLexiconEntry );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );
		$entry = $lexiconHandler->getEntry( 'sv', 'tomten' );
		$this->assertNotNull( $entry );
	}

	public function testGetEntry_nonExistingInLocal_retrieved() {
		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $this->mockedLexiconEntry );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$localMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( null );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );
		$entry = $lexiconHandler->getEntry( 'sv', 'tomten' );
		$this->assertNotNull( $entry );
	}

	// tests for get with merge

	/**
	 * One item that exists only in local lexicon.
	 * One identical item that exists in both local and Speechoid lexicon.
	 */
	public function testGetEntry_localOnlyAndIntersecting_fails() {
		$intersectingItem = new LexiconEntryItem();
		$intersectingItem->setProperties( [
			'id' => 0,
			'foo' => 'bar'
		] );

		$localOnlyItem = new LexiconEntryItem();
		$localOnlyItem->setProperties( [
			'id' => 0,
			'foo' => 'bass'
		] );

		$localEntry = new LexiconEntry();
		$localEntry->setKey( 'tomten' );
		$localEntry->setLanguage( 'sv' );
		$localEntry->setItems( [ $localOnlyItem, $intersectingItem ] );

		$speechoidEntry = new LexiconEntry();
		$speechoidEntry->setKey( 'tomten' );
		$speechoidEntry->setLanguage( 'sv' );
		$speechoidEntry->setItems( [ $intersectingItem ] );

		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $speechoidEntry );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$localMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $localEntry );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );
		$this->expectExceptionMessage(
			'Storages out of sync. 1 entry items from local and Speechoid lexicon failed to merge.'
		);
		$lexiconHandler->getEntry(
			'sv',
			'tomten'
		);
	}

	/**
	 * One item that exists only in Speechoid lexicon
	 * One identical item that exists in both local and Speechoid lexicon.
	 */
	public function testGetEntry_speechoidOnlyAndIntersecting_mergedAll() {
		$intersectingItem = new LexiconEntryItem();
		$intersectingItem->setProperties( [
			'id' => 0,
			'foo' => 'bar'
		] );

		$speechoidOnlyItem = new LexiconEntryItem();
		$speechoidOnlyItem->setProperties( [
			'id' => 1,
			'foo' => 'bar'
		] );

		$localEntry = new LexiconEntry();
		$localEntry->setKey( 'tomten' );
		$localEntry->setLanguage( 'sv' );
		$localEntry->setItems( [ $intersectingItem ] );

		$speechoidEntry = new LexiconEntry();
		$speechoidEntry->setKey( 'tomten' );
		$speechoidEntry->setLanguage( 'sv' );
		$speechoidEntry->setItems( [ $speechoidOnlyItem, $intersectingItem ] );

		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $speechoidEntry );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$localMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $localEntry );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );
		$mergedEntry = $lexiconHandler->getEntry(
			'sv',
			'tomten'
		);
		$this->assertCount( 2, $mergedEntry->getItems() );
		$this->assertContains( $speechoidOnlyItem, $mergedEntry->getItems() );
		$this->assertContains( $intersectingItem, $mergedEntry->getItems() );
	}

	/**
	 * The same identity on an item in both,
	 * local is said to be uploaded to Speechoid,
	 * but item contents differ.
	 *
	 * This simulates that Speechoid has been wiped clean.
	 *
	 * We should choose the local item, but we don't know how.
	 */
	public function testGetEntry_reinstalledSpeechoidLexicon_fails() {
		$localItem = new LexiconEntryItem();
		$localItem->setProperties( [
			'id' => 123,
			'foo' => 'locally changed before reinstall of Speechoid',
			'status' => [
				// notice that timestamp is older than the reinstalled speechoid item.
				'timestamp' => '2017-06-18T08:51:25Z'
			]
		] );

		$speechoidItem = new LexiconEntryItem();
		$speechoidItem->setProperties( [
			'id' => 123,
			'foo' => 'reinstalled',
			'status' => [
				// notice that timestamp is newer than the locally changed item.
				'timestamp' => '2018-06-18T08:51:25Z'
			]
		] );

		$localEntry = new LexiconEntry();
		$localEntry->setKey( 'tomten' );
		$localEntry->setLanguage( 'sv' );
		$localEntry->setItems( [ $localItem ] );

		$speechoidEntry = new LexiconEntry();
		$speechoidEntry->setKey( 'tomten' );
		$speechoidEntry->setLanguage( 'sv' );
		$speechoidEntry->setItems( [ $speechoidItem ] );

		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $speechoidEntry );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$localMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $localEntry );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );

		$this->expectExceptionMessage(
			'Storages out of sync. 1 entry items from local and Speechoid lexicon failed to merge.'
		);
		$lexiconHandler->getEntry(
			'sv',
			'tomten'
		);
	}

	// create and update is in fact the same function in LexiconHandler

	public function testCreateEntryItem_nonExisting_createdInLocalAndSpeechoid() {
		$item = new LexiconEntryItem();
		$item->setProperties( [ 'no identity' => 'none set' ] );

		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->never() )
			->method( 'getEntry' );
		$speechoidMock
			->expects( $this->once() )
			->method( 'createEntryItem' )
			->with( 'sv', 'tomten', $item );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$localMock
			->expects( $this->never() )
			->method( 'getEntry' );
		$localMock
			->expects( $this->never() )
			->method( 'entryItemExists' );
		$localMock
			->expects( $this->once() )
			->method( 'createEntryItem' )
			->with( 'sv', 'tomten', $item );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );
		$lexiconHandler->createEntryItem(
			'sv',
			'tomten',
			$item
		);
	}

	// @todo test with failed add to speechoid, failed to add local, and failed to add both

	public function testUpdateEntryItem_existingInBoth_updatedInBoth() {
		$item = new LexiconEntryItem();
		$item->setProperties( [ 'id' => 0 ] );

		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->never() )
			->method( 'getEntry' );
		$speechoidMock
			->expects( $this->never() )
			->method( 'createEntryItem' );
		$speechoidMock
			->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with( 'sv', 'tomten', $item );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$entry = new LexiconEntry();
		$entry->setItems( [ $item ] );
		$localMock
			->method( 'getEntry' )
			->willReturn( $entry );
		$localMock
			->expects( $this->once() )
			->method( 'entryItemExists' )
			->with( 'sv', 'tomten', $item )
			->willReturn( true );
		$localMock
			->expects( $this->never() )
			->method( 'createEntryItem' );
		$localMock
			->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with( 'sv', 'tomten', $item );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );
		$lexiconHandler->updateEntryItem(
			'sv',
			'tomten',
			$item
		);
	}

	public function testUpdateEntryItem_updatedItemPreferred_preferredRemovedFromOtherItems() {
		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );

		$item1 = new LexiconEntryItem();
		$item1->setProperties( [
			'id' => 0,
			'preferred' => true
		] );
		$item2 = new LexiconEntryItem();
		$item2->setProperties( [ 'id' => 1 ] );
		$localEntry = new LexiconEntry();
		$localEntry->setKey( 'tomten' );
		$localEntry->setLanguage( 'sv' );
		$localEntry->setItems( [ $item1, $item2 ] );
		$localMock
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $localEntry );
		$localMock
			->method( 'entryItemExists' )
			->willReturn( true );

		$newItem1 = new LexiconEntryItem();
		$newItem1->setProperties( [ 'id' => 0 ] );
		$newItem2 = new LexiconEntryItem();
		$newItem2->setProperties( [
			'id' => 1,
			'preferred' => true
		] );

		$localMock
			->expects( $this->exactly( 2 ) )
			->method( 'updateEntryItem' )
			->withConsecutive(
				[ 'sv', 'tomten', $newItem2 ],
				[ 'sv', 'tomten', $newItem1 ]
			);

		$lexiconHandler->updateEntryItem(
			'sv',
			'tomten',
			$newItem2
		);
	}

	public function testUpdateEntryItem_itemAlreadyPreferred_dontUpdateOtherItems() {
		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );

		$item1 = new LexiconEntryItem();
		$item1->setProperties( [
			'id' => 0,
			'preferred' => true,
			'parameter' => 'old'
		] );
		$item2 = new LexiconEntryItem();
		$item2->setProperties( [ 'id' => 1 ] );
		$localEntry = new LexiconEntry();
		$localEntry->setKey( 'tomten' );
		$localEntry->setLanguage( 'sv' );
		$localEntry->setItems( [ $item1, $item2 ] );
		$localMock
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $localEntry );
		$localMock
			->method( 'entryItemExists' )
			->willReturn( true );

		$newItem1 = new LexiconEntryItem();
		$newItem1->setProperties( [
			'id' => 0,
			'preferred' => true,
			'parameter' => 'new'
		] );

		$localMock
			->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with( 'sv', 'tomten', $newItem1 );

		$lexiconHandler->updateEntryItem(
			'sv',
			'tomten',
			$newItem1
		);
	}

public function testCreateEntryItem_newItemPreferred_preferredRemovedFromOtherItems() {
		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );

		$item1 = new LexiconEntryItem();
		$item1->setProperties( [
			'id' => 0,
			'preferred' => true
		] );
		$localEntry = new LexiconEntry();
		$localEntry->setKey( 'tomten' );
		$localEntry->setLanguage( 'sv' );
		$localEntry->setItems( [ $item1 ] );
		$localMock
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $localEntry );
		$localMock
			->method( 'entryItemExists' )
			->with( 'sv', 'tomten', $item1 )
			->willReturn( true );

		$newItem1 = new LexiconEntryItem();
		$newItem1->setProperties( [ 'id' => 0 ] );
		$newItem2 = new LexiconEntryItem();
		$newItem2->setProperties( [
			'preferred' => true
		] );
		$newItem2WithId = new LexiconEntryItem();
		$newItem2WithId->setProperties( [
			'id' => 1,
			'preferred' => true
		] );

		$localMock
			->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with( 'sv', 'tomten', $newItem1 );

		$lexiconHandler->createEntryItem(
			'sv',
			'tomten',
			$newItem2
		);
}

	/**
	 * Updates as item that exists in Speechoid but not in local storage.
	 * Current revision should be retrieved from Speechoid and created in local,
	 * then new item updated in speechoid,
	 * and finally new item updated in local.
	 */
	public function testUpdateEntryItem_existingOnlyInSpeechoid_currentCreatedInLocalUpdatedInBoth() {
		$entryCurrent = new LexiconEntry();
		$entryCurrent->setKey( 'tomten' );
		$entryCurrent->setLanguage( 'sv' );
		$itemCurrent = new LexiconEntryItem();
		$itemCurrent->setProperties( [ 'id' => 0, 'value' => 'initial' ] );
		$entryCurrent->setItems( [ $itemCurrent ] );

		$item = new LexiconEntryItem();
		$item->setProperties( [ 'id' => 0, 'value' => 'updated' ] );

		$speechoidMock = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidMock
			->expects( $this->once() )
			->method( 'getEntry' )
			->with( 'sv', 'tomten' )
			->willReturn( $entryCurrent );
		$speechoidMock
			->expects( $this->never() )
			->method( 'createEntryItem' );
		$speechoidMock
			->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with( 'sv', 'tomten', $item );

		$localMock = $this->createMock( LexiconWanCacheStorage::class );
		$localMock
			->expects( $this->never() )
			->method( 'getEntry' );
		$localMock
			->expects( $this->once() )
			->method( 'entryItemExists' )
			->with( 'sv', 'tomten', $item )
			->willReturn( false );
		$localMock
			->expects( $this->once() )
			->method( 'createEntryItem' )
			->with( 'sv', 'tomten', $itemCurrent );
		$localMock
			->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with( 'sv', 'tomten', $item );

		$lexiconHandler = new LexiconHandler( $speechoidMock, $localMock );
		$lexiconHandler->updateEntryItem(
			'sv',
			'tomten',
			$item
		);
	}
}

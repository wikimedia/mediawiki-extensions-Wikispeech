<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use FormatJson;
use HashBagOStuff;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWikiUnitTestCase;
use MWException;
use Status;

/**
 * @since 0.1.8
 * @covers \MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage
 */
class LexiconSpeechoidStorageTest extends MediaWikiUnitTestCase {

	/** @var string */
	private $mockedLexiconEntryJson = '[
  {
    "id": 808498,
    "lexRef": {
      "dbRef": "sv_se_nst_lex",
      "lexName": "sv-se.nst"
    },
    "strn": "tomten",
    "language": "sv-se",
    "partOfSpeech": "NN",
    "morphology": "SIN|DEF|NOM|UTR",
    "wordParts": "tomten",
    "lemma": {
      "id": 92909,
      "strn": "tomte",
      "paradigm": "s2b-båge"
    },
    "transcriptions": [
      {
        "id": 814660,
        "entryId": 808498,
        "strn": "\"\" t O m . t e n",
        "language": "sv-se",
        "sources": [
          "nst"
        ]
      }
    ],
    "status": {
      "id": 808498,
      "name": "imported",
      "source": "nst",
      "timestamp": "2018-06-18T08:51:25Z",
      "current": true
    }
  },
  {
    "id": 808499,
    "lexRef": {
      "dbRef": "sv_se_nst_lex",
      "lexName": "sv-se.nst"
    },
    "strn": "tomten",
    "language": "sv-se",
    "partOfSpeech": "NN",
    "morphology": "SIN|DEF|NOM|UTR",
    "wordParts": "tomten",
    "lemma": {
      "id": 92907,
      "strn": "tomt",
      "paradigm": "s3u-bild,dam"
    },
    "transcriptions": [
      {
        "id": 814661,
        "entryId": 808499,
        "strn": "\" t O m . t e n",
        "language": "sv-se",
        "sources": [
          "nst"
        ]
      }
    ],
    "status": {
      "id": 808499,
      "name": "imported",
      "source": "nst",
      "timestamp": "2018-06-18T08:51:25Z",
      "current": true
    }
  }
]';

	/** @var array */
	private $mockedLexiconEntry;

	/** @var HashBagOStuff */
	private $cache;

	protected function setUp(): void {
		parent::setUp();
		$status = FormatJson::parse( $this->mockedLexiconEntryJson, FormatJson::FORCE_ASSOC );
		if ( !$status->isOK() ) {
			throw new MWException( 'Failed to parse mocked JSON' );
		}
		$this->mockedLexiconEntry = $status->getValue();

		$this->cache = new HashBagOStuff();
		$cacheKey = $this->cache->makeKey( LexiconSpeechoidStorage::CACHE_CLASS, 'sv' );
		$this->cache->set( $cacheKey, 'sv_se_nst_lex:sv-se.nst' );
	}

	public function testGetEntry() {
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

		$connectorMock = $this->createMock( SpeechoidConnector::class );
		$connectorMock
			->expects( $this->once() )
			->method( 'lookupLexiconEntries' )
			->willReturn( Status::newGood( $this->mockedLexiconEntry ) );

		$speechoidStorage = new LexiconSpeechoidStorage( $connectorMock, $this->cache );
		$lexiconEntry = $speechoidStorage->getEntry( 'sv', 'tomten' );
		$this->assertSame( 'sv', $lexiconEntry->getLanguage() );
		$this->assertSame( 'tomten', $lexiconEntry->getKey() );
		$this->assertCount( 2, $lexiconEntry->getItems() );
		$this->assertSame( 808498, $lexiconEntry->getItems()[0]->getProperties()['id'] );
		$this->assertSame( 808499, $lexiconEntry->getItems()[1]->getProperties()['id'] );
	}

	public function testCreateEntry() {
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

		$connectorMock = $this->createPartialMock(
			SpeechoidConnector::class,
			[ 'lookupLexiconEntries', 'addLexiconEntry' ]
		);
		$connectorMock
			->expects( $this->once() )
			->method( 'lookupLexiconEntries' )
			->willReturn( Status::newGood( $this->mockedLexiconEntry ) );
		$connectorMock
			->expects( $this->once() )
			->method( 'addLexiconEntry' )
			->willReturn( Status::newGood( 808499 ) );

		$item = new LexiconEntryItem();
		$item->setProperties( [ 'whatever' => 'mock overrides this' ] );
		$speechoidStorage = new LexiconSpeechoidStorage( $connectorMock, $this->cache );
		$speechoidStorage->createEntryItem( 'sv', 'tomten', $item );
		$this->assertSame( 808499, $item->getProperties()['id'] );
	}

	public function testUpdateEntry_identityGiven_receivedUpdatedItem() {
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

		$connectorMock = $this->createPartialMock(
			SpeechoidConnector::class,
			[ 'lookupLexiconEntries', 'updateLexiconEntry' ]
		);
		$connectorMock
			->expects( $this->once() )
			->method( 'lookupLexiconEntries' )
			->willReturn( Status::newGood( $this->mockedLexiconEntry ) );
		$connectorMock
			->expects( $this->once() )
			->method( 'updateLexiconEntry' )
			->willReturn( Status::newGood( $this->mockedLexiconEntry[1] ) );

		$item = new LexiconEntryItem();
		$item->setProperties(
			[
				'id' => 808499,
				'whatever' => 'mock overrides this'
			]
		);
		$speechoidStorage = new LexiconSpeechoidStorage( $connectorMock, $this->cache );
		$speechoidStorage->updateEntryItem( 'sv', 'tomten', $item );
		$this->assertSame( 808499, $item->getProperties()['id'] );
	}

	public function testUpdateEntry_identityNotGive_throwsException() {
		$connectorMock = $this->createPartialMock(
			SpeechoidConnector::class,
			[ 'lookupLexiconEntries', 'updateLexiconEntry' ]
		);
		$connectorMock
			->expects( $this->never() )
			->method( 'updateLexiconEntry' );

		$item = new LexiconEntryItem();
		$item->setProperties( [ 'whatever' => 'mock overrides this' ] );
		$speechoidStorage = new LexiconSpeechoidStorage( $connectorMock, $this->cache );
		$this->expectExceptionMessage( 'Speechoid identity not set.' );
		$speechoidStorage->updateEntryItem( 'sv', 'tomten', $item );
	}

}

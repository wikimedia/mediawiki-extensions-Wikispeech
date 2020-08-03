<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers UtteranceStore
 */
class UtteranceStoreTest extends MediaWikiTestCase {

	/**
	 * @var TestingAccessWrapper $utteranceStore wrapped instance of {@link UtteranceStore}
	 */
	private $utteranceStore;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	protected function setUp() : void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgWikispeechUtteranceFileBackendContainerName' => 'foo_container',
			'wgWikispeechUtteranceFileBackendName' => '',
		] );
		$this->tablesUsed[] = UtteranceStore::UTTERANCE_TABLE;
		$this->utteranceStore = TestingAccessWrapper::newFromObject( new UtteranceStore() );
		// use new empty transient file backend
		// @todo Investigate mocking of file backend. See https://phabricator.wikimedia.org/T255126
		$this->utteranceStore->fileBackend = new MemoryFileBackend( [
			'name' => 'wikispeech_utterances',
			'wikiId' => WikiMap::getCurrentWikiId()
		] );
		$this->logger = LoggerFactory::getInstance( 'UtteranceStoreTest' );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetDefaultVoice_definedExisting_definedDefaultVoice() {
		$this->setMwGlobals( [
			'wgWikispeechVoices' => [
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		] );
		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'bertil',
					'en' => 'bravo'
				]
			);
		$this->utteranceStore->speechoidConnector = $speechoidConnectorMock;
		$this->assertEquals( 'bertil', $this->utteranceStore->getDefaultVoice( 'sv' ) );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetDefaultVoice_definedNonExisting_fallbackToFirstVoice() {
		$this->setMwGlobals( [
			'wgWikispeechVoices' => [
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		] );
		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'en' => 'bravo'
				]
			);
		$this->utteranceStore->speechoidConnector = $speechoidConnectorMock;

		$this->assertEquals( 'adam', $this->utteranceStore->getDefaultVoice( 'sv' ) );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetDefaultVoice_definedFalsy_fallbackToFirstVoice() {
		$this->setMwGlobals( [
			'wgWikispeechVoices' => [
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ],
				'no' => [ 'anne', 'bergit', 'clark' ],
			]
		] );
		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->exactly( 3 ) )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => '',
					'en' => false,
					'no' => null
				]
			);
		$this->utteranceStore->speechoidConnector = $speechoidConnectorMock;

		$this->assertEquals( 'adam', $this->utteranceStore->getDefaultVoice( 'sv' ) );
		$this->assertEquals( 'alpha', $this->utteranceStore->getDefaultVoice( 'en' ) );
		$this->assertEquals( 'anne', $this->utteranceStore->getDefaultVoice( 'no' ) );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetDefaultVoice_unsupported_null() {
		$this->setMwGlobals( [
			'wgWikispeechVoices' => [
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		] );
		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'en' => 'bravo'
				]
			);
		$this->utteranceStore->speechoidConnector = $speechoidConnectorMock;

		$this->assertNull( $this->utteranceStore->getDefaultVoice( 'sv' ) );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetDefaultVoice_definedDefaultNonExistingLanguage_null() {
		$this->setMwGlobals( [
			'wgWikispeechVoices' => [
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		] );
		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'adam',
					'en' => 'bravo'
				]
			);
		$this->utteranceStore->speechoidConnector = $speechoidConnectorMock;

		$this->assertNull( $this->utteranceStore->getDefaultVoice( 'sv' ) );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetDefaultVoice_definedDefaultNoVoicesInExistingLanguage_null() {
		$this->setMwGlobals( [
			'wgWikispeechVoices' => [
				'sv' => [],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		] );
		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'adam',
					'en' => 'bravo'
				]
			);
		$this->utteranceStore->speechoidConnector = $speechoidConnectorMock;

		$this->assertNull( $this->utteranceStore->getDefaultVoice( 'sv' ) );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetDefaultVoice_definedDefaultNotRegisteredInLanguage_null() {
		$this->setMwGlobals( [
			'wgWikispeechVoices' => [
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		] );
		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'david',
					'en' => 'bravo'
				]
			);
		$this->utteranceStore->speechoidConnector = $speechoidConnectorMock;

		$this->assertNull( $this->utteranceStore->getDefaultVoice( 'sv' ) );
	}

	/**
	 * Tries to fetch something that doesn't exist.
	 * @since 0.1.5
	 */
	public function testFetchNonExistingUtterance() {
		$this->assertNull(
			$this->utteranceStore->findUtterance( 1, 'sv', 'anna', 'invalid' )
		);
	}

	/**
	 * Creates an utterance in the database,
	 * asserts that identity was set,
	 *
	 * @since 0.1.5
	 * @throws MWException
	 */
	public function testCreateUtterance() {
		$data = [
			'pageId' => 1,
			'language' => 'sv',
			'voice' => 'bertil',
			'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
			'audio' => 'DummyBase64Audio=',
			'synthesisMetadata' =>
				'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}'
		];
		$started = MWTimestamp::getInstance();
		$created = $this->utteranceStore->createUtterance(
			$data['pageId'],
			$data['language'],
			$data['voice'],
			$data['segmentHash'],
			$data['audio'],
			$data['synthesisMetadata']
		);
		$this->assertTrue( is_int( $created[ 'utteranceId' ] ) );
		$this->assertTrue( $started <= $created['dateStored'] );
		$this->assertSame( $data['pageId'], $created['pageId'] );
		$this->assertSame( $data['language'], $created['language'] );
		$this->assertSame( $data['voice'], $created['voice'] );
		$this->assertSame( $data['segmentHash'], $created['segmentHash'] );
		$this->assertSelect(
			UtteranceStore::UTTERANCE_TABLE,
			[ 'wsu_page_id', 'wsu_lang', 'wsu_seg_hash', 'wsu_voice' ],
			[ 'wsu_utterance_id' => $created['utteranceId'] ],
			[ [ $data['pageId'], $data['language'], $data['segmentHash'], $data['voice'] ] ]
		);
	}

	/**
	 * Creates an utterance in the database,
	 * asserts that values match,
	 * asserts that identity was set,
	 * asserts that created timestamp was set.
	 *
	 * @since 0.1.5
	 */
	public function testFindUtterance() {
		$data = [
			'pageId' => 1,
			'language' => 'sv',
			'voice' => 'bertil',
			'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
			'audio' => 'DummyBase64Audio=',
			'synthesisMetadata' =>
				'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}'
		];
		$this->utteranceStore->createUtterance(
			$data['pageId'],
			$data['language'],
			$data['voice'],
			$data['segmentHash'],
			$data['audio'],
			$data['synthesisMetadata']
		);
		// find the utterance we created and ensure it matches.
		$retrieved = $this->utteranceStore->findUtterance(
			$data['pageId'],
			$data['language'],
			$data['voice'],
			$data['segmentHash']
		);
		// assert database values are set
		$this->assertSame( $data['pageId'], $retrieved['pageId'] );
		$this->assertSame( $data['language'], $retrieved['language'] );
		$this->assertSame( $data['voice'], $retrieved['voice'] );
		$this->assertSame( $data['segmentHash'], $retrieved['segmentHash'] );
		// assert values from file store is loaded
		$this->assertEquals( $data['audio'], $retrieved['audio'] );
		$this->assertEquals( $data['synthesisMetadata'], $retrieved['synthesisMetadata'] );
	}

	/**
	 * Asserts functionally of paths in file backend,
	 * which avoids overloading directories with files.
	 *
	 * @since 0.1.5
	 */
	public function testUrlPathFactoryRootOneCharacterInteger() {
		$this->assertEquals(
			'/',
			$this->utteranceStore->urlPathFactory( 1 )
		);
	}

	/**
	 * Asserts functionally of paths in file backend,
	 * which avoids overloading directories with files.
	 *
	 * @since 0.1.5
	 */
	public function testUrlPathFactoryRootTwoCharacterInteger() {
		$this->assertEquals(
			'/',
			$this->utteranceStore->urlPathFactory( 12 )
		);
	}

	/**
	 * Asserts functionally of paths in file backend,
	 * which avoids overloading directories with files.
	 *
	 * @since 0.1.5
	 */
	public function testUrlPathFactoryRootThreeCharacterInteger() {
		$this->assertEquals(
			'/',
			$this->utteranceStore->urlPathFactory( 123 )
		);
	}

	/**
	 * Asserts functionally of paths in file backend,
	 * which avoids overloading directories with files.
	 *
	 * @since 0.1.5
	 */
	public function testUrlPathFactoryFourCharacterInteger() {
		$this->assertEquals(
			'/1/',
			$this->utteranceStore->urlPathFactory( 1234 )
		);
	}

	/**
	 * Asserts functionally of paths in file backend,
	 * which avoids overloading directories with files.
	 *
	 * @since 0.1.5
	 */
	public function testUrlPathFactoryFiveCharacterInteger() {
		$this->assertEquals(
			'/1/2/',
			$this->utteranceStore->urlPathFactory( 12345 )
		);
	}

	/**
	 * Asserts functionally of paths in file backend,
	 * which avoids overloading directories with files.
	 *
	 * @since 0.1.5
	 */
	public function testUrlPathFactorySixCharacterInteger() {
		$this->assertEquals(
			'/1/2/3/',
			$this->utteranceStore->urlPathFactory( 123456 )
		);
	}

	/**
	 * Asserts functionally of paths in file backend,
	 * which avoids overloading directories with files.
	 *
	 * @since 0.1.5
	 */
	public function testUrlPathFactorySevenCharacterInteger() {
		$this->assertEquals(
			'/1/2/3/4/',
			$this->utteranceStore->urlPathFactory( 1234567 )
		);
	}

	public function testFlushUtterancesByExpirationDate() {
		$dateExpires = 20020101000000;
		$mockedUtterances = [
			[
				'utteranceId' => null,
				'dateStored' => $dateExpires - 10000000000,
				'expectedToFlush' => true,
				'pageId' => 2,
				'language' => 'sv',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			], [
				'utteranceId' => null,
				'dateStored' => $dateExpires + 10000000000,
				'expectedToFlush' => false,
				'pageId' => 3,
				'language' => 'sv',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			],
		];

		$expectedFlushCounter = $this
			->insertMockedDataForFlushTestsAndReturnExpectedNumberToBeFlushed( $mockedUtterances );

		// actually flush
		$flushedCount = $this->utteranceStore->flushUtterancesByExpirationDate(
			MWTimestamp::getInstance( $dateExpires )
		);

		$this->assertFlushed( $mockedUtterances, $expectedFlushCounter, $flushedCount );
	}

	public function testFlushUtterancesByLanguageAndVoice() {
		$mockedUtterances = [
			[
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => false,
				'pageId' => 1,
				'language' => 'sv',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			], [
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => true,
				'pageId' => 2,
				'language' => 'sv',
				'voice' => 'bertil',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			], [
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => false,
				'pageId' => 2,
				'language' => 'en',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			]
		];

		$expectedFlushCounter = $this
			->insertMockedDataForFlushTestsAndReturnExpectedNumberToBeFlushed( $mockedUtterances );

		$flushedCount = $this->utteranceStore->flushUtterancesByLanguageAndVoice( 'sv', 'bertil' );

		$this->assertFlushed( $mockedUtterances, $expectedFlushCounter, $flushedCount );
	}

	public function testFlushUtterancesByLanguage() {
		$mockedUtterances = [
			[
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => true,
				'pageId' => 1,
				'language' => 'sv',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			], [
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => true,
				'pageId' => 2,
				'language' => 'sv',
				'voice' => 'bertil',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			], [
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => false,
				'pageId' => 2,
				'language' => 'en',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			]
		];

		$expectedFlushCounter = $this
			->insertMockedDataForFlushTestsAndReturnExpectedNumberToBeFlushed( $mockedUtterances );

		$flushedCount = $this->utteranceStore->flushUtterancesByLanguageAndVoice( 'sv' );

		$this->assertFlushed( $mockedUtterances, $expectedFlushCounter, $flushedCount );
	}

	public function testFlushUtterancesByPage() {
		$mockedUtterances = [
			[
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => true,
				'pageId' => 1,
				'language' => 'sv',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			], [
				'utteranceId' => null,
				'dateStored' => 20020101000000,
				'expectedToFlush' => false,
				'pageId' => 2,
				'language' => 'sv',
				'voice' => 'anna',
				'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
				'audio' => 'DummyBase64Audio=',
				'synthesisMetadata' =>
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}',
			]
		];

		$expectedFlushCounter = $this
			->insertMockedDataForFlushTestsAndReturnExpectedNumberToBeFlushed( $mockedUtterances );

		$flushedCount = $this->utteranceStore->flushUtterancesByPage( 1 );

		$this->assertFlushed( $mockedUtterances, $expectedFlushCounter, $flushedCount );
	}

	/**
	 * Inserts mocked utterances to database and file backend.
	 * Sets utteranceId from database to mocked utterances.
	 *
	 * @param array &$mockedUtterances Array of array with mocked utterances
	 * @return int Number of mocked utterances with key 'expectedToFlush' set to true.
	 */
	private function insertMockedDataForFlushTestsAndReturnExpectedNumberToBeFlushed(
		&$mockedUtterances
	) {
		// insert test utterances
		foreach ( $mockedUtterances as &$mockedUtterance ) {
			$this->db->insert( UtteranceStore::UTTERANCE_TABLE, [
				'wsu_date_stored' => strval( $mockedUtterance['dateStored'] ),
				'wsu_page_id' => $mockedUtterance['pageId'],
				'wsu_lang' => $mockedUtterance['language'],
				'wsu_voice' => $mockedUtterance['voice'],
				'wsu_seg_hash' => $mockedUtterance['segmentHash']
			] );
			$mockedUtterance['utteranceId'] = $this->db->insertId();
			$this->assertTrue( is_int( $mockedUtterance['utteranceId'] ) );

			// create audio file
			$audioUrl = $this->utteranceStore->audioUrlFactory(
				$mockedUtterance['utteranceId']
			);
			$this->assertTrue( $this->utteranceStore->fileBackend->prepare( [
				'dir' => dirname( $audioUrl ),
				'noAccess' => 1,
				'noListing' => 1
			] )->isOK() );
			$this->assertTrue( $this->utteranceStore->fileBackend->create( [
				'dst' => $audioUrl,
				'content' => $mockedUtterance['audio']
			] )->isOK() );

			// create synthesis metadata file
			$synthesisMetadataUrl = $this->utteranceStore
				->synthesisMetadataUrlFactory( $mockedUtterance['utteranceId'] );
			$this->assertTrue( $this->utteranceStore->fileBackend->prepare( [
				'dir' => dirname( $synthesisMetadataUrl ),
				'noAccess' => 1,
				'noListing' => 1
			] )->isOK() );
			$this->assertTrue( $this->utteranceStore->fileBackend->create( [
				'dst' => $synthesisMetadataUrl,
				'content' => $mockedUtterance['synthesisMetadata']
			] )->isOK() );
			$this->logger->debug(
				'Inserted utterance {utterance} from mock', [
					'utterance' => $mockedUtterance
			] );
		}
		unset( $mockedUtterance );

		// count number of utterances that are supposed to flush out.
		$expectedFlushCounter = 0;
		foreach ( $mockedUtterances as $mockedUtterance ) {
			if ( $mockedUtterance['expectedToFlush'] ) {
				$expectedFlushCounter++;
				$this->logger->debug(
					'Expecting to flush {mockedUtterance}', [
						'mockedUtterance' => $mockedUtterance
				] );
			}
		}

		return $expectedFlushCounter;
	}

	/**
	 * @param array &$mockedUtterances Array of array with mocked utterances
	 * @param int $expectedFlushCounter
	 * @param int $flushedCount
	 * @throws MWException
	 */
	private function assertFlushed(
		array $mockedUtterances,
		$expectedFlushCounter,
		$flushedCount
	) {
		$this->assertEquals( $expectedFlushCounter, $flushedCount );

		// ensure expected flushed utterances is gone.
		foreach ( $mockedUtterances as $mockedUtterance ) {
			$this->logger->debug(
				'Inspecting {mockedUtterance}', [
					'mockedUtterance' => $mockedUtterance
			] );
			$this->assertTrue( is_int( $mockedUtterance['utteranceId'] ) );
			if ( $mockedUtterance['expectedToFlush'] ) {
				// utterance should have been flushed out
				$this->assertFalse(
					$this->utteranceStore->fileBackend->fileExists(
						[ 'src' => $this->utteranceStore
							->audioUrlFactory( $mockedUtterance['utteranceId'] ) ]
					)
				);
				$this->assertFalse(
					$this->utteranceStore->fileBackend->fileExists(
						[ 'src' => $this->utteranceStore
							->synthesisMetadataUrlFactory( $mockedUtterance['utteranceId'] ) ]
					)
				);
				$this->assertSelect(
					UtteranceStore::UTTERANCE_TABLE,
					[ 'wsu_utterance_id' ],
					[ 'wsu_utterance_id' => $mockedUtterance['utteranceId'] ],
					[]
				);
			} else {
				// utterance should not have been flushed out
				$this->assertTrue(
					$this->utteranceStore->fileBackend->fileExists(
						[ 'src' => $this->utteranceStore
							->audioUrlFactory( $mockedUtterance['utteranceId'] ) ]
					)
				);
				$this->assertTrue(
					$this->utteranceStore->fileBackend->fileExists(
						[ 'src' => $this->utteranceStore
							->synthesisMetadataUrlFactory( $mockedUtterance['utteranceId'] ) ]
					)
				);
				$this->assertSelect(
					UtteranceStore::UTTERANCE_TABLE,
					[ 'wsu_utterance_id' ],
					[ 'wsu_utterance_id' => $mockedUtterance['utteranceId'] ],
					[ [ $mockedUtterance['utteranceId'] ] ]
				);
			}
		}
	}
}

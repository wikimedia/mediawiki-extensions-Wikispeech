<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\WikiMap\WikiMap;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWikiIntegrationTestCase;
use MemoryFileBackend;
use MWTimestamp;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \MediaWiki\Wikispeech\Utterance\UtteranceStore
 */
class UtteranceStoreTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var TestingAccessWrapper|UtteranceStore
	 */
	private $utteranceStore;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'WikispeechUtteranceFileBackendContainerName' => 'foo_container',
			'WikispeechUtteranceFileBackendName' => '',
		] );
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
	 * Tries to fetch something that doesn't exist.
	 * @since 0.1.5
	 */
	public function testFetchNonExistingUtterance() {
		$this->assertNull(
			$this->utteranceStore->findUtterance( null, 1, 'sv', 'anna', 'invalid' )
		);
	}

	public static function provideTestCreateAndFindUtterance() {
		return [
			'withConsumerUrl' => [
				[
					'consumerUrl' => 'http://foo.bar/wiki',
					'remoteWikiHash' => '2386df4fdb39d2f492614cd81e53b642019fcc7ff932d93f81214035e40c6971',
					'pageId' => 1,
					'language' => 'sv',
					'voice' => 'bertil',
					'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
					'audio' => 'DummyBase64Audio=',
					'synthesisMetadata' =>
						'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}'
				]
			],
			'noConsumerUrl' => [
				[
					'consumerUrl' => null,
					'remoteWikiHash' => null,
					'pageId' => 1,
					'language' => 'sv',
					'voice' => 'bertil',
					'segmentHash' => '1234567890123456789012345678901234567890123456789012345678901234',
					'audio' => 'DummyBase64Audio=',
					'synthesisMetadata' =>
						'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}'
				]
			]
		];
	}

	/**
	 * Creates an utterance in the database,
	 * asserts that identity was set,
	 *
	 * @dataProvider provideTestCreateAndFindUtterance
	 * @since 0.1.5
	 * @param array $data
	 * @throws RuntimeException
	 */
	public function testCreateUtterance( array $data ) {
		$started = MWTimestamp::getInstance();
		$created = $this->utteranceStore->createUtterance(
			$data['consumerUrl'],
			$data['pageId'],
			$data['language'],
			$data['voice'],
			$data['segmentHash'],
			$data['audio'],
			$data['synthesisMetadata']
		);
		$this->assertSame( $data['remoteWikiHash'], $created->getRemoteWikiHash() );
		$this->assertTrue( is_int( $created->getUtteranceId() ) );
		$this->assertTrue( $started <= $created->getDateStored() );
		$this->assertSame( $data['pageId'], $created->getPageId() );
		$this->assertSame( $data['language'], $created->getLanguage() );
		$this->assertSame( $data['voice'], $created->getVoice() );
		$this->assertSame( $data['segmentHash'], $created->getSegmentHash() );
		$this->assertSelect(
			UtteranceStore::UTTERANCE_TABLE,
			[ 'wsu_remote_wiki_hash', 'wsu_page_id', 'wsu_lang', 'wsu_seg_hash', 'wsu_voice' ],
			[ 'wsu_utterance_id' => $created->getUtteranceId() ],
			[ [ $data['remoteWikiHash'], $data['pageId'], $data['language'], $data['segmentHash'], $data['voice'] ] ]
		);
	}

	/**
	 * Creates an utterance in the database,
	 * fetches the utterance using findUtterance,
	 * asserts that values match
	 *
	 * @dataProvider provideTestCreateAndFindUtterance
	 * @since 0.1.5
	 * @param array $data
	 */
	public function testFindUtterance( array $data ) {
		$this->utteranceStore->createUtterance(
			$data['consumerUrl'],
			$data['pageId'],
			$data['language'],
			$data['voice'],
			$data['segmentHash'],
			$data['audio'],
			$data['synthesisMetadata']
		);
		// find the utterance we created and ensure it matches.
		$retrieved = $this->utteranceStore->findUtterance(
			$data['consumerUrl'],
			$data['pageId'],
			$data['language'],
			$data['voice'],
			$data['segmentHash']
		);
		$this->assertNotNull( $retrieved, 'Unable to find newly created utterance!' );
		// assert database values are set
		$this->assertSame( $data['remoteWikiHash'], $retrieved->getRemoteWikiHash() );
		$this->assertSame( $data['pageId'], $retrieved->getPageId() );
		$this->assertSame( $data['language'], $retrieved->getLanguage() );
		$this->assertSame( $data['voice'], $retrieved->getVoice() );
		$this->assertSame( $data['segmentHash'], $retrieved->getSegmentHash() );
		// assert values from file store is loaded
		$this->assertEquals( $data['audio'], $retrieved->getAudio() );
		$this->assertEquals( $data['synthesisMetadata'], $retrieved->getSynthesisMetadata() );
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

		$flushedCount = $this->utteranceStore->flushUtterancesByPage( null, 1 );

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
			$this->getDb()->insert( UtteranceStore::UTTERANCE_TABLE, [
				'wsu_date_stored' => $this->getDb()->timestamp( $mockedUtterance['dateStored'] ),
				'wsu_page_id' => $mockedUtterance['pageId'],
				'wsu_lang' => $mockedUtterance['language'],
				'wsu_voice' => $mockedUtterance['voice'],
				'wsu_seg_hash' => $mockedUtterance['segmentHash']
			] );
			$mockedUtterance['utteranceId'] = $this->getDb()->insertId();
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
	 * @param array $mockedUtterances Array of array with mocked utterances
	 * @param int $expectedFlushCounter
	 * @param int $flushedCount
	 * @throws RuntimeException
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

	public function testFlushUtterancesByExpirationDateOnFile_emptyFileBackend_success() {
		$this->assertSame( 0, $this->utteranceStore
			->flushUtterancesByExpirationDateOnFile( MWTimestamp::getInstance() ) );
	}

	// phpcs:ignore Generic.Files.LineLength
	public function testFlushUtterancesByExpirationDateOnFile_fastForwardClock_firstKeptThenRemoved() {
		$before = new MWTimestamp( strtotime( '-15 minutes' ) );

		// create audio file
		$mockedUtterance = [
			'utteranceId' => 12345678,
			'audio' => 'DummyBase64Audio=',
			'synthesisMetadata' => '{ "foo": "bar" }'
		];
		$audioUrl = $this->utteranceStore->audioUrlFactory(
			$mockedUtterance['utteranceId']
		);
		$this->logger->debug( 'Creating {url}', [ 'url' => $audioUrl ] );
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
		$synthesisMetadataUrl = $this->utteranceStore->synthesisMetadataUrlFactory(
			$mockedUtterance['utteranceId']
		);
		$this->logger->debug( 'Creating {url}', [ 'url' => $synthesisMetadataUrl ] );
		$this->assertTrue( $this->utteranceStore->fileBackend->prepare( [
			'dir' => dirname( $synthesisMetadataUrl ),
			'noAccess' => 1,
			'noListing' => 1
		] )->isOK() );
		$this->assertTrue( $this->utteranceStore->fileBackend->create( [
			'dst' => $synthesisMetadataUrl,
			'content' => $mockedUtterance['synthesisMetadata']
		] )->isOK() );

		$this->assertSame( 0, $this->utteranceStore
			->flushUtterancesByExpirationDateOnFile( $before ) );

		// assert files are still there
		$this->assertTrue(
			$this->utteranceStore->fileBackend
				->fileExists( [ 'src' => $audioUrl ] )
		);
		$this->assertTrue(
			$this->utteranceStore->fileBackend
				->fileExists( [ 'src' => $synthesisMetadataUrl ] )
		);

		$future = strtotime( '+15 minutes' );

		$this->assertSame( 2, $this->utteranceStore
			->flushUtterancesByExpirationDateOnFile( new MWTimestamp( $future ) ) );

		// assert files are deleted
		$this->assertFalse(
			$this->utteranceStore->fileBackend
				->fileExists( [ 'src' => $audioUrl ] )
		);
		$this->assertFalse(
			$this->utteranceStore->fileBackend
				->fileExists( [ 'src' => $synthesisMetadataUrl ] )
		);
	}
}

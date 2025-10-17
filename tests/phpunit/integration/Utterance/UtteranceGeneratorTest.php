<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use FormatJson;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\SegmentList;
use MediaWiki\Wikispeech\Segment\SegmentMessagesFactory;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWiki\Wikispeech\Segment\SegmentPageResponse;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\Utterance\Utterance;
use MediaWiki\Wikispeech\Utterance\UtteranceGenerator;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWikiIntegrationTestCase;
use MWTimestamp;
use WANObjectCache;

/**
 * @covers \MediaWiki\Wikispeech\Utterance\UtteranceGenerator
 */
class UtteranceGeneratorTest extends MediaWikiIntegrationTestCase {

	/**
	 * @since 0.1.11
	 */
	public function testGetUtterance_requestNewUtterance_speechoidConnectorExecuted() {
		$hash = '4466ca9fbdfc6c9cf9c53de4e5e373d6b60d023338e9a9f9ff8e6ddaef36a3e4';
		$content = 'Word 1 Word 2 Word 3.';
		$segment = new Segment(
			[ new CleanedText( $content, './div/p/text()' ) ],
			12,
			22,
			$hash
		);
		$synthesizeMetadataJson =
			'[' .
			'{"endtime": 295, "orth": "Word"}, ' .
			'{"endtime": 510, "expanded": "one", "orth": "1"}, ' .
			'{"endtime": 800, "orth": "Word"}, ' .
			'{"endtime": 930, "expanded": "two", "orth": "2"}, ' .
			'{"endtime": 1215, "orth": "Word"}, ' .
			'{"endtime": 1565, "expanded": "three", "orth": "3"}, ' .
			'{"endtime": 1565, "orth": "."}, ' .
			'{"endtime": 1975, "orth": ""}' .
			']';
		$synthesizeMetadataArray = FormatJson::parse(
			$synthesizeMetadataJson,
			FormatJson::FORCE_ASSOC
		)->getValue();

		// Ensure that JSON string is formatted the same way as in utterance store.
		// This is to match the string expected in the mocked UtteranceStore#createUtterance,
		// as the value from the mocked Speechoid connector
		// (the de-serialized JSON synthesis metadata PHP array)
		// will be re-serialized to a JSON string and then passed on to the createUtterance function.
		$synthesizeMetadataJson = FormatJson::encode( $synthesizeMetadataArray );

		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->method( 'findUtterance' )
			->with(
				// $consumerUrl, $pageId, $language, $voice, $segmentHash, $omitAudio = false
				$this->isNull(),
				2,
				'sv',
				'anna',
				$hash,
				false
			)
			->willReturn( null );
		$utteranceStoreMock
			->expects( $this->once() )
			->method( 'createUtterance' )
			->with(
				// $consumerUrl, $pageId, $language, $voice, $segmentHash, $audio, $synthesisMetadata
				$this->isNull(),
				2,
				'sv',
				'anna',
				$hash,
				'DummyBase64==',
				// this is the reason for re-serializing the JSON string .
				$synthesizeMetadataJson
			);

		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'synthesize' )
			->willReturn( [
				"audio_data" => "DummyBase64==",
				"tokens" => $synthesizeMetadataArray
			] );

		$utteranceGenerator = new UtteranceGenerator(
			$speechoidConnectorMock,
			$utteranceStoreMock,
			$this->createMock( SegmentPageFactory::class ),
			$this->createStub( WANObjectCache::class ),
			$this->createMock( SegmentMessagesFactory::class )
		);

		$utterance = $utteranceGenerator->getUtterance(
			null,
			'anna',
			'sv',
			2,
			$segment
		);

		$this->assertSame( 'DummyBase64==', $utterance['audio'] );
		$this->assertSame( $synthesizeMetadataArray, $utterance['tokens'] );
	}

	/**
	 * @since 0.1.11
	 */
	public function testGetUtterance_requestExistingUtterance_speechoidConnectorNotExecuted() {
		$hash = '4466ca9fbdfc6c9cf9c53de4e5e373d6b60d023338e9a9f9ff8e6ddaef36a3e4';
		$content = 'Word 1 Word 2 Word 3.';
		$segment = new Segment(
			[ new CleanedText( $content, './div/p/text()' ) ],
			12,
			22,
			$hash
		);
		$synthesizeMetadataJson =
			'[' .
			'{"endtime": 295, "orth": "Word"}, ' .
			'{"endtime": 510, "expanded": "one", "orth": "1"}, ' .
			'{"endtime": 800, "orth": "Word"}, ' .
			'{"endtime": 930, "expanded": "two", "orth": "2"}, ' .
			'{"endtime": 1215, "orth": "Word"}, ' .
			'{"endtime": 1565, "expanded": "three", "orth": "3"}, ' .
			'{"endtime": 1565, "orth": "."}, ' .
			'{"endtime": 1975, "orth": ""}' .
			']';
		$synthesizeMetadataArray = FormatJson::parse(
			$synthesizeMetadataJson,
			FormatJson::FORCE_ASSOC
		)->getValue();

		$mockedFoundUtterance = new Utterance(
			1,
			null,
			null,
			2,
			'sv',
			'anna',
			$hash,
			MWTimestamp::getInstance( 20020101000000 )
		);
		$mockedFoundUtterance->setAudio( 'DummyBase64==' );
		$mockedFoundUtterance->setSynthesisMetadata( $synthesizeMetadataJson );

		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->method( 'findUtterance' )
			->with(
				// $consumerUrl, $pageId, $language, $voice, $segmentHash, $omitAudio = false=
				$this->isNull(),
				2,
				'sv',
				'anna',
				$hash,
				false
			)
			->willReturn( $mockedFoundUtterance );

		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->never() )
			->method( 'synthesizeText' );

		$utteranceGenerator = new UtteranceGenerator(
			$speechoidConnectorMock,
			$utteranceStoreMock,
			$this->createMock( SegmentPageFactory::class ),
			$this->createStub( WANObjectCache::class ),
			$this->createMock( SegmentMessagesFactory::class )
		);

		$utterance = $utteranceGenerator->getUtterance(
			null,
			'anna',
			'sv',
			2,
			$segment
		);

		$this->assertSame( 'DummyBase64==', $utterance['audio'] );
		$this->assertSame( $synthesizeMetadataArray, $utterance['tokens'] );
	}

	public function testGetUtteranceForRevisionAndSegment_utteranceExists_giveUtterance() {
		$services = MediaWikiServices::getInstance();
		$segmentPageFactory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$services->getMainWANObjectCache(),
				$services->getMainConfig(),
				$services->getRevisionFactory(),
				$services->getHttpRequestFactory()
			] )
			->onlyMethods( [ 'segmentPage' ] )
			->getMock();
		$response = new SegmentPageResponse();
		$segment = new Segment( hash: 'SEGMENT HASH' );
		$segmentList = new SegmentList( [ $segment ] );
		$response->setSegments( $segmentList );
		$response->setPageId( 123 );
		$response->setRevisionId( 456 );
		$segmentPageFactory->method( 'segmentPage' )->willReturn( $response );
		$speechoidConnector = $this->createMock( SpeechoidConnector::class );
		$speechoidConnector->method( 'synthesizeText' )->willReturn(
			[
				'audio_data' => 'AUDIO',
				'tokens' => [ 'TOKEN1', 'TOKEN2' ]
			]
		);
		$utteranceGenerator = new UtteranceGenerator(
			$speechoidConnector,
			$this->createMock( UtteranceStore::class ),
			$segmentPageFactory,
			$this->createStub( WANObjectCache::class ),
			$this->createMock( SegmentMessagesFactory::class )
		);
		$utteranceGenerator->setContext( new RequestContext() );

		$utterance = $utteranceGenerator->getUtteranceForRevisionAndSegment(
			'anna',
			'sv',
			456,
			'SEGMENT HASH'
		);

		$expectedUtterance = [
			'audio' => 'AUDIO',
			'tokens' => [ 'TOKEN1', 'TOKEN2' ]
		];
		$this->assertSame( $expectedUtterance, $utterance );
	}
}

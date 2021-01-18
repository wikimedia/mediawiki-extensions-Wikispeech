<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ApiMain;
use ApiTestCase;
use ApiUsageException;
use FormatJson;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Wikispeech\Api\ApiWikispeechListen;
use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MWException;
use MWTimestamp;
use WANObjectCache;
use Wikimedia\TestingAccessWrapper;

/**
 * @group medium
 * @covers \MediaWiki\Wikispeech\Api\ApiWikispeechListen
 */
class ApiWikispeechListenTest extends ApiTestCase {

	protected function setUp() : void {
		parent::setUp();
		// Should be implementable using
		// $wgConfigRegistry['wikispeech'] see T255497
		$this->setMwGlobals( [
			// Make sure we don't send requests to an actual server.
			'wgWikispeechSpeechoidUrl' => '',
			'wgWikispeechVoices' => [
				'ar' => [ 'ar-voice' ],
				'en' => [
					'en-voice1',
					'en-voice2'
				],
				'sv' => [ 'sv-voice' ]
			],
			'wgWikispeechListenMaximumInputCharacters' => 60
		] );
	}

	protected function tearDown(): void {
		WikiPageTestUtil::removeCreatedPages();
		parent::tearDown();
	}

	public function testInvalidLanguage() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'"xx" is not a valid language. Should be one of: "ar", "en", "sv".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'text' => 'Text to listen to.',
			'lang' => 'xx'
		] );
	}

	public function testInvalidVoice() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'"invalid-voice" is not a valid voice. Should be one of: "en-voice1", "en-voice2".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'text' => 'Text to listen to.',
			'lang' => 'en',
			'voice' => 'invalid-voice'
		] );
	}

	/**
	 * @since 0.1.5
	 */
	public function testValidInputLength() {
		$api = $this->mockApi();
		$api->validateParameters( [
			'action' => 'wikispeech-listen',
			'text' => 'This is a short sentence with less than 60 characters.',
			'lang' => 'en',
			'voice' => ''
		] );
		// What we really want to do here is to assert that
		// ApiUsageException is not thrown.
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Create a mocked API object.
	 *
	 * @since 0.1.7
	 * @return ApiWikispeechListen|TestingAccessWrapper
	 */
	private function mockApi() {
		$wanObjectCache = $this->createStub( WanObjectCache::class );
		$revisionStore = $this->createStub( RevisionStore::class );
		$api = TestingAccessWrapper::newFromObject( new ApiWikispeechListen(
			new ApiMain(), '', $wanObjectCache, $revisionStore
		) );
		return $api;
	}

	/**
	 * @since 0.1.5
	 */
	public function testInvalidInputLength() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'Input text must not exceed 60 characters, but contained '
			. '64 characters.'
		);
		$api = $this->mockApi();
		$api->validateParameters( [
			'action' => 'wikispeech-listen',
			'text' => 'This is a tiny bit longer sentence with more than 60 characters.',
			'lang' => 'en',
			'voice' => ''
		] );
	}

	public function testRequest_revisionParameterAndNoSegmentParameter_exceptionRaised() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'The "revision" parameter may only be used with "segment".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'revision' => 1,
			'lang' => 'en',
			'voice' => ''
		] );
	}

	public function testRequest_segmentParameterAndNoRevisionParameter_exceptionRaised() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'The "segment" parameter may only be used with "revision".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'segment' => 'hash1234',
			'lang' => 'en',
			'voice' => ''
		] );
	}

	public function testRequest_revisionAndTextParametersGiven_exceptionRaised() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'The "text" parameter cannot be used with "revision".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'revision' => 1,
			'segment' => 'hash1234',
			'text' => 'Text to listen to.',
			'lang' => 'en',
			'voice' => ''
		] );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetUtterance_requestNewUtterance_speechoidConnectorExecuted() {
		$hash = '4466ca9fbdfc6c9cf9c53de4e5e373d6b60d023338e9a9f9ff8e6ddaef36a3e4';
		$content = 'Word 1 Word 2 Word 3.';
		$segment = [
			'startOffset' => 12,
			'endOffset' => 22,
			'content' => [ new CleanedText( $content, './div/p/text()' ) ],
			'hash' => $hash
		];
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

		$api = $this->mockApi();

		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->method( 'findUtterance' )
			->with(
				// $pageId, $language, $voice, $segmentHash, $omitAudio = false
				$this->equalTo( 2 ),
				$this->equalTo( 'sv' ),
				$this->equalTo( 'anna' ),
				$this->equalTo( $hash ),
				$this->equalTo( false )
			)
			->willReturn( null );
		$utteranceStoreMock
			->method( 'createUtterance' )
			->with(
				// $pageId, $language, $voice, $segmentHash, $audio, $synthesisMetadata
				$this->equalTo( 2 ),
				$this->equalTo( 'sv' ),
				$this->equalTo( 'anna' ),
				$this->equalTo( $hash ),
				$this->equalTo( 'DummyBase64==' ),
				// this is the reason for re-serializing the JSON string .
				$this->equalTo( $synthesizeMetadataJson )
			);
		$api->utteranceStore = $utteranceStoreMock;

		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->once() )
			->method( 'synthesize' )
			->with(
				$this->equalTo( 'sv' ),
				$this->equalTo( 'anna' ),
				$this->equalTo( $content )
			)
			->willReturn( [
				"audio_data" => "DummyBase64==",
				"tokens" => $synthesizeMetadataArray
			] );
		$api->speechoidConnector = $speechoidConnectorMock;

		$utterance = $api->getUtterance(
			'anna',
			'sv',
			2,
			$segment
		);

		$this->assertSame( 'DummyBase64==', $utterance['audio'] );
		$this->assertSame( $synthesizeMetadataArray, $utterance['tokens'] );
	}

	/**
	 * @since 0.1.5
	 */
	public function testGetUtterance_requestExistingUtterance_speechoidConnectorNotExecuted() {
		$hash = '4466ca9fbdfc6c9cf9c53de4e5e373d6b60d023338e9a9f9ff8e6ddaef36a3e4';
		$content = 'Word 1 Word 2 Word 3.';
		$segment = [
			'startOffset' => 12,
			'endOffset' => 22,
			'content' => [ new CleanedText( $content, './div/p/text()' ) ],
			'hash' => $hash
		];
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

		$api = $this->mockApi();

		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->method( 'findUtterance' )
			->with(
				// $pageId, $language, $voice, $segmentHash, $omitAudio = false
				$this->equalTo( 2 ),
				$this->equalTo( 'sv' ),
				$this->equalTo( 'anna' ),
				$this->equalTo( $hash ),
				$this->equalTo( false )
			)
			->willReturn( [
				'utteranceId' => 1,
				'pageId' => 2,
				'language' => 'sv',
				'voice' => 'anna',
				'segmentHash' => $hash,
				'dateStored' => MWTimestamp::getInstance( 20020101000000 ),
				'audio' => 'DummyBase64==',
				'synthesisMetadata' => $synthesizeMetadataJson,
			] );
		$api->utteranceStore = $utteranceStoreMock;

		$speechoidConnectorMock = $this->createMock( SpeechoidConnector::class );
		$speechoidConnectorMock
			->expects( $this->never() )
			->method( 'synthesize' );
		$api->speechoidConnector = $speechoidConnectorMock;

		$utterance = $api->getUtterance(
			'anna',
			'sv',
			2,
			$segment
		);

		$this->assertSame( 'DummyBase64==', $utterance['audio'] );
		$this->assertSame( $synthesizeMetadataArray, $utterance['tokens'] );
	}

	public function testRequest_notCurrentRevision_passedToSegmenter() {
		$page = WikiPageTestUtil::addPage( 'Page', 'Old' );
		$oldId = $page->getLatest();

		// Making an edit causes the initial revision to be non-current.
		WikiPageTestUtil::editPage( $page, 'New' );

		// Purposefully hit the Segmenter Exception to avoid actual synthesis
		$this->expectException( MWException::class );
		$this->expectExceptionMessage(
			'An outdated or invalid revision id was provided'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'revision' => $oldId,
			'segment' => 'hash',
			'lang' => 'en'
		] );
	}

	public function testRequest_deletedRevision_throwsException() {
		$testUser = self::getTestUser()->getUser();
		$page = WikiPageTestUtil::addPage( 'Page', 'Old' );
		$oldId = $page->getLatest();
		// Delete the page and by extension the revision.
		$page->doDeleteArticleReal(
			'No reason',
			$testUser,
			false
		);

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'Deleted revisions cannot be listened to.'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'revision' => $oldId,
			'segment' => 'hash',
			'lang' => 'en'
		] );
	}

	public function testRequest_suppressedRevision_throwsException() {
		// Set up a user with permission to supress revisions
		$this->mergeMwGlobalArrayValue(
			'wgGroupPermissions',
			[ 'sysop' => [ 'deleterevision' => true ] ]
		);
		$testSysop = $this->getTestSysop()->getUser();

		$page = WikiPageTestUtil::addPage( 'Page', 'Old' );
		$oldId = $page->getLatest();

		// Making an edit causes the initial revision to be non-current.
		WikiPageTestUtil::editPage( $page, 'New' );

		// supress the old revision
		$this->doApiRequest( [
			'action' => 'revisiondelete',
			'type' => 'revision',
			'target' => $page->getTitle()->getDbKey(),
			'ids' => $oldId,
			'hide' => 'content',
			'token' => $testSysop->getEditToken(),
		] );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'Deleted revisions cannot be listened to.'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'revision' => $oldId,
			'segment' => 'hash',
			'lang' => 'en'
		] );
	}

	public function testRequest_suppressedRevisionAllowedUser_throwsException() {
		// Set up a user with permission to supress revisions and view the same
		$this->mergeMwGlobalArrayValue(
			'wgGroupPermissions',
			[ 'sysop' => [
				'deleterevision' => true,
				'deletedtext' => true
			] ]
		);
		$testSysop = $this->getTestSysop()->getUser();

		$page = WikiPageTestUtil::addPage( 'Page', 'Old' );
		$oldId = $page->getLatest();

		// Making an edit causes the initial revision to be non-current.
		WikiPageTestUtil::editPage( $page, 'New' );

		// supress the old revision
		$this->doApiRequest( [
			'action' => 'revisiondelete',
			'type' => 'revision',
			'target' => $page->getTitle()->getDbKey(),
			'ids' => $oldId,
			'hide' => 'content',
			'token' => $testSysop->getEditToken(),
		] );

		// Purposefully hit the Segmenter Exception to avoid actual synthesis
		$this->expectException( MWException::class );
		$this->expectExceptionMessage(
			'An outdated or invalid revision id was provided'
		);
		// do request as $testSysop
		$this->doApiRequest(
			[
				'action' => 'wikispeech-listen',
				'revision' => $oldId,
				'segment' => 'hash',
				'lang' => 'en'
			],
			null, false, $testSysop
	 );
	}
}

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
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Wikispeech\Api\ApiWikispeechListen;
use MediaWiki\Wikispeech\Segment\OutdatedOrInvalidRevisionException;
use MediaWiki\Wikispeech\Utterance\UtteranceGenerator;
use WANObjectCache;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @group Database
 * @covers \MediaWiki\Wikispeech\Api\ApiWikispeechListen
 */
class ApiWikispeechListenTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Should be implementable using
		// $wgConfigRegistry['wikispeech'] see T255497

		$this->overrideConfigValues( [
			// Make sure we don't send requests to an actual server.
			'WikispeechSpeechoidUrl' => '',
			'WikispeechVoices' => [
				'ar' => [ 'ar-voice' ],
				'en' => [
					'en-voice1',
					'en-voice2'
				],
				'sv' => [ 'sv-voice' ]
			],
			'WikispeechListenMaximumInputCharacters' => 60,
		] );
	}

	protected function tearDown(): void {
		WikiPageTestUtil::removeCreatedPages();
		parent::tearDown();
	}

	public function testApiRequest_invalidLanguage_throwException() {
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

	public function testApiRequest_invalidVoice_throwException() {
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
	public function testValidateParameters_validInputLength_noException() {
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
		$wanObjectCache = $this->createStub( WANObjectCache::class );
		$revisionStore = $this->createStub( RevisionStore::class );
		$requestFactory = $this->createStub( HttpRequestFactory::class );
		$utteranceGeneratorMock = $this->createStub( UtteranceGenerator::class );
		$api = TestingAccessWrapper::newFromObject( new ApiWikispeechListen(
			new ApiMain(),
			'',
			$wanObjectCache,
			$revisionStore,
			$requestFactory,
			$utteranceGeneratorMock,
			''
		) );
		return $api;
	}

	/**
	 * @since 0.1.5
	 */
	public function testValidateParameters_invalidInputLength_throwException() {
		$api = $this->mockApi();

		try {
			$api->validateParameters( [
				'action' => 'wikispeech-listen',
				'text' => 'This is a tiny bit longer sentence with more than 60 characters.',
				'lang' => 'en',
				'voice' => ''
			] );
			$this->fail( 'Expected ApiUsageException not thrown.' );
		} catch ( ApiUsageException $e ) {
			$this->assertStringContainsString(
				'Input text must not exceed 60 characters',
				$e->getMessage()
			);
		}
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
			'The parameters "text" and "revision" can not be used together.'
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

	public function testRequest_notCurrentRevision_passedToSegmenter() {
		$page = WikiPageTestUtil::addPage( 'Page', 'Old' );
		$oldId = $page->getLatest();

		// Making an edit causes the initial revision to be non-current.
		WikiPageTestUtil::editPage( $page, 'New' );

		// Purposefully hit the Segmenter Exception to avoid actual synthesis
		$this->expectException( OutdatedOrInvalidRevisionException::class );
		$this->expectExceptionMessage(
			'An outdated or invalid revision id was provided'
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'revision' => $oldId,
			'segment' => 'hash',
			'lang' => 'en',
			'voice' => 'en-voice1'
		] );
	}

	public function testRequest_deletedRevision_throwException() {
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
			'lang' => 'en',
			'voice' => 'en-voice1'
		] );
	}

	public function testRequest_suppressedRevision_throwException() {
		// Set up a user with permission to supress revisions
		$this->setGroupPermissions( [
			'sysop' => [
				'deleterevision' => true,
				'deletedtext' => false
			],
		] );
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
			'lang' => 'en',
			'voice' => 'en-voice1'
		] );
	}

	public function testRequest_suppressedRevisionAllowedUser_throwException() {
		// Set up a user with permission to supress revisions and view the same
		$this->setGroupPermissions( [
			'sysop' => [
				'deleterevision' => true,
				'deletedtext' => true
			],
		] );
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
		$this->expectException( OutdatedOrInvalidRevisionException::class );
		$this->expectExceptionMessage(
			'An outdated or invalid revision id was provided'
		);
		// do request as $testSysop
		$this->doApiRequest(
			[
				'action' => 'wikispeech-listen',
				'revision' => $oldId,
				'segment' => 'hash',
				'lang' => 'en',
				'voice' => 'en-voice1'
			],
			null, false, $testSysop
		);
	}

	public function testRequest_consumerUrlGivenNotInProducerMode_throwException() {
		$this->overrideConfigValue( 'WikispeechProducerMode', false );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Requests from remote wikis are not allowed.' );

		$this->doApiRequest( [
			'action' => 'wikispeech-listen',
			'revision' => 1,
			'segment' => 'hash',
			'lang' => 'en',
			'consumer-url' => 'https://consumer.url'
		] );
	}
}

<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use InvalidArgumentException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\SegmentBreak;
use MediaWiki\Wikispeech\Segment\Segmenter;
use MediaWiki\Wikispeech\Tests\WikiPageTestUtil;
use MediaWikiIntegrationTestCase;
use MWException;
use RequestContext;
use Title;
use WANObjectCache;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @covers \MediaWiki\Wikispeech\Segment\Segmenter
 */
class SegmenterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var TestingAccessWrapper|Segmenter
	 */
	private $segmenter;

	protected function setUp() : void {
		parent::setUp();
		$this->requestFactory = $this->createMock( HttpRequestFactory::class );
		$this->segmenter = TestingAccessWrapper::newFromObject(
			new Segmenter(
				new RequestContext(),
				MediaWikiServices::getInstance()->getMainWANObjectCache(),
				$this->requestFactory
			)
		);
	}

	protected function tearDown(): void {
		WikiPageTestUtil::removeCreatedPages();
		parent::tearDown();
	}

	/**
	 * Activates a WANCache previously disabled by MediaWikiTestCase.
	 *
	 * @since 0.1.8
	 */
	private function activateWanCache() {
		$this->setMwGlobals( [
			'wgMainWANCache' => 'hash',
			'wgWANObjectCaches' => [
				'hash' => [
					'class'    => WANObjectCache::class,
					'cacheId'  => 'hash',
					'channels' => []
				]
			]
		] );
		$this->segmenter->cache =
			MediaWikiServices::getInstance()->getMainWANObjectCache();
	}

	/**
	 * Replace Segmenter instance by one where cleanPage is mocked.
	 *
	 * @since 0.1.8
	 * @param int|null $occurences If provided adds an assertion that cleanPage is
	 *  called exactly this many times.
	 * @throws InvalidArgumentException If occurences is not a positive integer.
	 */
	private function mockCleanPage( $occurences = null ) {
		if ( !is_int( $occurences ) || $occurences < 0 ) {
			throw new InvalidArgumentException(
				'$occurences must be a positive integer' );
		}

		$segmenterMock = $this->getMockBuilder( Segmenter::class )
			->setConstructorArgs( [
				new RequestContext(),
				MediaWikiServices::getInstance()->getMainWANObjectCache(),
				MediaWikiServices::getInstance()->getHttpRequestFactory()
			] )
			->onlyMethods( [ 'cleanPage' ] )
			->getMock();
		$segmenterMock
			->expects( $this->exactly( $occurences ) )
			->method( 'cleanPage' )
			->willReturn( [] );
		$this->segmenter = $segmenterMock;
	}

	public function testSegmentPage_contentContainsSentences_giveTitleAndContent() {
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		WikiPageTestUtil::addPage( $titleString, $content );
		$title = Title::newFromText( $titleString );
		$expectedSegments = [
			[
				'startOffset' => 0,
				'endOffset' => 3,
				'content' => [ new CleanedText( 'Page', '//h1[@id="firstHeading"]//text()' ) ],
				'hash' => 'cd2c3fb786ef2a8ba5430f54cde3d468c558647bf0fd777b437e8138e2348e01'
			],
			[
				'startOffset' => 0,
				'endOffset' => 10,
				'content' => [ new CleanedText( 'Sentence 1.', './div/p/text()' ) ],
				'hash' => '76ca3069cee56491f5b2f465c4e9b57b7fb74ebc12eecc0cd6aad965ea7e247e'
			],
			[
				'startOffset' => 12,
				'endOffset' => 22,
				'content' => [ new CleanedText( 'Sentence 2.', './div/p/text()' ) ],
				'hash' => '33dc64326df9f4b281fc9d680f89423f3261d1056d857a8263d46f7904a705ac'
			],
			[
				'startOffset' => 24,
				'endOffset' => 34,
				'content' => [ new CleanedText( 'Sentence 3.', './div/p/text()' ) ],
				'hash' => 'bae6b55875cd8e8bee3b760773f36a3a25e2d6fa102f168aade3d49f77c34da6'
			]
		];
		$segments = $this->segmenter->segmentPage( $title, [], [] );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testSegmentPage_setDisplayTitle_segmentDisplayTitle() {
		$titleString = 'Title';
		$content = '{{DISPLAYTITLE:title}}Some content text.';
		WikiPageTestUtil::addPage( $titleString, $content );
		$title = Title::newFromText( $titleString );
		$expectedSegments = [
			[
				'startOffset' => 0,
				'endOffset' => 4,
				'content' => [ new CleanedText( 'title', '//h1[@id="firstHeading"]//text()' ) ],
				'hash' => '1ec72b6861fee9926d828a734ddbd533a1eb1a983d42acec571720deb2b92018'
			],
			[
				'startOffset' => 0,
				'endOffset' => 17,
				'content' => [ new CleanedText( 'Some content text.', './div/p/text()' ) ],
				'hash' => '3eb8e91dc31a98b63aebe35a1229364deced3f3abbc26eb09fe67394e5cd5c0f'
			]
		];
		$segments = $this->segmenter->segmentPage( $title, [], [] );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testSegmentPage_repeatedRequest_useCache() {
		// make sure we have a working cache
		$this->activateWanCache();
		// mock cleanPage with single call
		$this->mockCleanPage( 1 );

		$page = WikiPageTestUtil::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$segments = $this->segmenter->segmentPage( $title, [], [] );

		$segmentsAgain = $this->segmenter->segmentPage( $title, [], [] );
		$this->assertEquals( $segments, $segmentsAgain );
	}

	public function testSegmentPage_differentParameters_dontUseCache() {
		$this->setMwGlobals( [
			'wgWikispeechSegmentBreakingTags' => [ 'br' ],
			'wgWikispeechRemoveTags' => [ 'del' => true ]
		] );
		// make sure we have a working cache
		$this->activateWanCache();
		// mock cleanPage with four calls
		$this->mockCleanPage( 4 );

		$page = WikiPageTestUtil::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$this->segmenter->segmentPage( $title );
		$this->segmenter->segmentPage( $title, [], [] );
		$this->segmenter->segmentPage( $title, [], [ 'br' ] );
		$this->segmenter->segmentPage( $title, [ 'del' => true ], [] );
	}

	public function testSegmentPage_noTagParametersGiven_useDefault() {
		$this->setMwGlobals( [
			'wgWikispeechSegmentBreakingTags' => [ 'br' ],
			'wgWikispeechRemoveTags' => [ 'del' => true ]
		] );
		$titleString = 'Page';
		$content = 'one<br />two<del>three</del>';
		WikiPageTestUtil::addPage( $titleString, $content );
		$title = Title::newFromText( $titleString );
		$expectedSegments = [
			[
				'startOffset' => 0,
				'endOffset' => 3,
				'content' => [ new CleanedText( 'Page', '//h1[@id="firstHeading"]//text()' ) ],
				'hash' => 'cd2c3fb786ef2a8ba5430f54cde3d468c558647bf0fd777b437e8138e2348e01'
			],
			[
				'startOffset' => 0,
				'endOffset' => 2,
				'content' => [ new CleanedText( 'one', './div/p/text()[1]' ) ],
				'hash' => '2c8b08da5ce60398e1f19af0e5dccc744df274b826abe585eaba68c525434806'
			],
			[
				'startOffset' => 0,
				'endOffset' => 1,
				'content' => [
					new CleanedText( 'two', './div/p/text()[2]' ),
					new CleanedText( "\n\n", './div/text()[3]' )
				],
				'hash' => 'ce53a6624b8515fa2bcf28d50690e8a52b77de083bb166879fb81d737af09cb1'
			]
		];
		$segments = $this->segmenter->segmentPage( $title );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testSegmentPage_missmatchedRevision_throwException() {
		// mock cleanPage with no calls
		$this->mockCleanPage( 0 );
		$this->expectException( MWException::class );
		// @todo implement exception code and replace by expectExceptionCode
		$this->expectExceptionMessage( 'An outdated or invalid revision id was provided' );

		$content = 'Foo';
		$page1 = WikiPageTestUtil::addPage( 'Page1', $content );
		$page2 = WikiPageTestUtil::addPage( 'Page2', $content );
		$title1 = $page1->getTitle();

		$revisionId2 = $page2->getLatest();
		$this->segmenter->segmentPage( $title1, [], [], $revisionId2 );
	}

	public function testSegmentPage_uncachedOldRevision_throwException() {
		// mock cleanPage with no calls
		$this->mockCleanPage( 0 );
		$this->expectException( MWException::class );
		// @todo implement exception code and replace by expectExceptionCode
		$this->expectExceptionMessage( 'An outdated or invalid revision id was provided' );

		$page = WikiPageTestUtil::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$revisionId = $page->getLatest();

		WikiPageTestUtil::editPage( $page, 'Bar' );
		$this->assertNotEquals( $revisionId, $page->getLatest() );
		$this->segmenter->segmentPage( $title, [], [], $revisionId );
	}

	public function testSegmentPage_cachedOldRevision_useCache() {
		// make sure we have a working cache
		$this->activateWanCache();
		// mock cleanPage with single calls
		$this->mockCleanPage( 1 );

		$page = WikiPageTestUtil::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$revisionId = $page->getLatest();
		$segments = $this->segmenter->segmentPage( $title, [], [], $revisionId );

		WikiPageTestUtil::editPage( $page, 'Bar' );
		$this->assertNotEquals( $revisionId, $page->getLatest() );
		$segmentsAgain = $this->segmenter->segmentPage( $title, [], [], $revisionId );

		$this->assertEquals( $segments, $segmentsAgain );
	}

	public function testSegmentPage_provideDefaultParameters_useCache() {
		$this->setMwGlobals( [
			'wgWikispeechSegmentBreakingTags' => [ 'br' ],
			'wgWikispeechRemoveTags' => [ 'del' => true ]
		] );
		// make sure we have a working cache
		$this->activateWanCache();
		// mock cleanPage with one call
		$this->mockCleanPage( 1 );

		$page = WikiPageTestUtil::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$this->segmenter->segmentPage( $title );
		$this->segmenter->segmentPage( $title, [ 'del' => true ], [ 'br' ] );
	}

	public function testSegmentPage_consumerUrlGiven_getPageFromConsumer() {
		$title = 'Page';
		$content = 'Content';
		$request = 'https://consumer.url/api.php?action=parse&format=json&page=Page&prop=text%7Crevid%7Cdisplaytitle';
		$this->requestFactory->method( 'get' )
			->with( $this->equalTo( $request ) )
			->willReturn( '{
	"parse": {
		"pageid": 1,
		"text": {
			"*": "Content"
		},
		"displaytitle": "Page"
	}
}'
			);
		$segments = $this->segmenter->segmentPage(
			$title, [], [], null, 'https://consumer.url'
		);
		$this->assertSame(
			'Page',
			$segments[0]['content'][0]->string
		);
		$this->assertSame(
			'Content',
			$segments[1]['content'][0]->string
		);
	}

	public function testCleanPage_contentAndTitleGiven_giveCleanedTextArray() {
		$title = 'Page';
		$content = '<p>Content</p>';

		$cleanedText = $this->segmenter->cleanPage( $title, $content, [], [] );

		$this->assertEquals(
			[
				new CleanedText( 'Page', '//h1[@id="firstHeading"]//text()' ),
				new SegmentBreak(),
				new CleanedText( 'Content', './p/text()' )
			],
			// For some reason, there are a number of HTML nodes
			// containing only newlines, which adds extra
			// CleanText's. They don't cause any issues in the end
			// though. See T255152.
			array_slice( $cleanedText, 0, 3 )
		);
	}

	public function testGetSegment_segmentExists_returnSegment() {
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		$page = WikiPageTestUtil::addPage( $titleString, $content );
		$title = $page->getTitle();
		$revisionId = $page->getLatest();
		$hash = '33dc64326df9f4b281fc9d680f89423f3261d1056d857a8263d46f7904a705ac';
		$expectedSegment = [
			'startOffset' => 12,
			'endOffset' => 22,
			'content' => [ new CleanedText( 'Sentence 2.', './div/p/text()' ) ],
			'hash' => $hash
		];
		$segment = $this->segmenter->getSegment( $title, $hash, $revisionId );
		$this->assertEquals( $expectedSegment, $segment );
	}

	public function testGetSegment_segmentDoesntExists_returnNull() {
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		$page = WikiPageTestUtil::addPage( $titleString, $content );
		$title = $page->getTitle();
		$revisionId = $page->getLatest();
		$hash = 'ThisHashMatchesNoSegment';
		$segment = $this->segmenter->getSegment( $title, $hash, $revisionId );
		$this->assertNull( $segment );
	}
}

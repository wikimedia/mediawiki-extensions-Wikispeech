<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @covers Segmenter
 */
class SegmenterTest extends MediaWikiTestCase {

	/**
	 * @var TestingAccessWrapper
	 */
	private $segmenter;

	protected function setUp() : void {
		parent::setUp();
		$this->segmenter = TestingAccessWrapper::newFromObject(
			new Segmenter( new RequestContext() )
		);
	}

	public function testSegmentPage() {
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		Util::addPage( $titleString, $content );
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
		Util::addPage( $titleString, $content );
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

	public function testCleanPage() {
		$content = 'Content';
		Util::addPage( 'Page', $content );
		$title = Title::newFromText( 'Page' );
		$page = WikiPage::factory( $title );

		$cleanedText = $this->segmenter->cleanPage( $page, [], [] );

		$this->assertEquals(
			[
				new CleanedText( 'Page', '//h1[@id="firstHeading"]//text()' ),
				new SegmentBreak(),
				new CleanedText( "Content\n", './div/p/text()' )
			],
			// For some reason, there are a number of HTML nodes
			// containing only newlines, which adds extra
			// CleanText's. They don't cause any issues in the end
			// though. See T255152.
			array_slice( $cleanedText, 0, 3 )
		);
	}

	public function testSegmentSentences() {
		$cleanedContent = [
			new CleanedText( 'Sentence 1. Sentence 2. Sentence 3.' )
		];
		$expectedSegments = [
			[
				'startOffset' => 0,
				'endOffset' => 10,
				'content' => [ new CleanedText( 'Sentence 1.' ) ],
				'hash' => '76ca3069cee56491f5b2f465c4e9b57b7fb74ebc12eecc0cd6aad965ea7e247e'
			],
			[
				'startOffset' => 12,
				'endOffset' => 22,
				'content' => [ new CleanedText( 'Sentence 2.' ) ],
				'hash' => '33dc64326df9f4b281fc9d680f89423f3261d1056d857a8263d46f7904a705ac'
			],
			[
				'startOffset' => 24,
				'endOffset' => 34,
				'content' => [ new CleanedText( 'Sentence 3.' ) ],
				'hash' => 'bae6b55875cd8e8bee3b760773f36a3a25e2d6fa102f168aade3d49f77c34da6'
			]
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByEllipses() {
		$cleanedContent = [
			new CleanedText( 'This is... one sentence.' )
			];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'This is... one sentence.' ) ],
			$segments[0]['content']
		);
	}

	public function testDontSegmentByAbbreviations() {
		$cleanedContent = [ new CleanedText( 'One sentence i.e. one segment.' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'One sentence i.e. one segment.' ) ],
			$segments[0]['content']
		);
	}

	public function testDontSegmentByDotDirectlyFollowedByComma() {
		$cleanedContent = [ new CleanedText( 'As with etc., jr. and friends.' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'As with etc., jr. and friends.' ) ],
			$segments[0]['content']
		);
	}

	public function testDontSegmentByDecimalDot() {
		$cleanedContent = [ new CleanedText( 'In numbers like 2.9.' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'In numbers like 2.9.' ) ],
			$segments[0]['content']
		);
	}

	public function testKeepLastSegmentEvenIfNotEndingWithSentenceFinalCharacter() {
		$cleanedContent = [ new CleanedText( 'Sentence. No sentence final' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'No sentence final' ) ],
			$segments[1]['content']
		);
		$this->assertEquals( 26, $segments[1]['endOffset'] );
	}

	public function testTextFromMultipleNodes() {
		$cleanedContent = [
			new CleanedText( 'Sentence split ' ),
			new CleanedText( 'by' ),
			new CleanedText( ' tags.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			0,
			$segments[0]['startOffset']
		);
		$this->assertEquals(
			5,
			$segments[0]['endOffset']
		);
		$this->assertEquals(
			'Sentence split ',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals(
			'by',
			$segments[0]['content'][1]->string
		);
		$this->assertEquals(
			' tags.',
			$segments[0]['content'][2]->string
		);
	}

	public function testStartOffsetForMultipleTextNodes() {
		$cleanedContent = [
			new CleanedText( 'First sentence. Split' ),
			new CleanedText( 'sentence. And other sentence.' ),
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			16,
			$segments[1]['startOffset']
		);
		$this->assertEquals(
			10,
			$segments[2]['startOffset']
		);
	}

	public function testTextOffset() {
		$cleanedContent = [ new CleanedText( 'Sentence.' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame( 0, $segments[0]['startOffset'] );
		$this->assertEquals( 8, $segments[0]['endOffset'] );
	}

	public function testSegmentTextWithUnicodeChars() {
		$cleanedContent = [
			new CleanedText(
				'Normal sentence. Utterance with å. Another normal sentence.'
			)
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Utterance with å.',
			$segments[1]['content'][0]->string
		);
		$this->assertEquals( 17, $segments[1]['startOffset'] );
		$this->assertEquals( 33, $segments[1]['endOffset'] );
		$this->assertEquals(
			'Another normal sentence.',
			$segments[2]['content'][0]->string
		);
		$this->assertEquals( 35, $segments[2]['startOffset'] );
		$this->assertEquals( 58, $segments[2]['endOffset'] );
	}

	public function testTextStartsWithSentenceFinalCharacter() {
		$cleanedContent = [
			new CleanedText( 'Sentence one' ),
			new CleanedText( '. Sentence two.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence one',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals(
			'.',
			$segments[0]['content'][1]->string
		);
		$this->assertEquals(
			'Sentence two.',
			$segments[1]['content'][0]->string
		);
	}

	public function testDontCreateEmptyTextForWhitespaces() {
		$cleanedContent = [
			new CleanedText( 'Sentence 1. ' ),
			new CleanedText( 'Sentence 2.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence 1.',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals(
			'Sentence 2.',
			$segments[1]['content'][0]->string
		);
	}

	public function testDontCreateEmptyTextForWhitespacesBetweenSegmentBreakingTags() {
		$cleanedContent = [
			new CleanedText( 'Text one' ),
			new SegmentBreak(),
			new CleanedText( ' ' ),
			new SegmentBreak(),
			new CleanedText( 'Text two' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Text one',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals(
			'Text two',
			$segments[1]['content'][0]->string
		);
	}

	public function testRemoveTextWithOnlyWhitespacesOutsideSegments() {
		$cleanedContent = [
			new CleanedText( ' ' ),
			new CleanedText( 'Sentence 1.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence 1.',
			$segments[0]['content'][0]->string
		);
	}

	public function testRemoveLeadingAndTrailingWhitespaces() {
		$cleanedContent = [ new CleanedText( ' Sentence. ' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence.',
			$segments[0]['content'][0]->string
		);
		$this->assertSame( 1, $segments[0]['startOffset'] );
		$this->assertEquals( 9, $segments[0]['endOffset'] );
	}

	public function testDontAddOnlyNewlineItem() {
		$cleanedContent = [
			new CleanedText( 'text' ),
			new CleanedText( "\n" )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			1,
			count( $segments[0]['content'] )
		);
		$this->assertEquals(
			'text',
			$segments[0]['content'][0]->string
		);
	}

	public function testLastTextIsOnlySentenceFinalCharacter() {
		$cleanedContent = [
			new CleanedText( 'Sentence one' ),
			new CleanedText( '. ' ),
			new CleanedText( 'Sentence two.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence one',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals(
			'.',
			$segments[0]['content'][1]->string
		);
		$this->assertEquals(
			'Sentence two.',
			$segments[1]['content'][0]->string
		);
	}

	public function testSegmentSentencesByTags() {
		$cleanedContent = [
			new CleanedText( 'Header' ),
			new SegmentBreak(),
			new CleanedText( 'Paragraph one' ),
			new SegmentBreak(),
			new CleanedText( 'Paragraph two' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Header',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals(
			'Paragraph one',
			$segments[1]['content'][0]->string
		);
		$this->assertEquals(
			'Paragraph two',
			$segments[2]['content'][0]->string
		);
	}

	public function testEvaluateHash() {
		// SHA256 of "Word 1 Word 2 Word 3."
		$expectedHash = '4466ca9fbdfc6c9cf9c53de4e5e373d6b60d023338e9a9f9ff8e6ddaef36a3e4';
		$segments = $this->segmenter->segmentSentences(
			[ new CleanedText( 'Word 1 Word 2 Word 3.' ) ]
		);
		$hash = $this->segmenter->evaluateHash( $segments[0] );
		$this->assertEquals( $expectedHash, $hash );
	}

	/**
	 * Activates a WANCache previously disabled by MediaWikiTestCase.
	 *
	 * @since 0.1.5
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
	}

	/**
	 * Replace Segmenter instance by one where cleanPage is mocked.
	 *
	 * @since 0.1.5
	 * @param int|null $occurences If provided adds an assertion that cleanPage
	 *  is called exactly this many times.
	 * @throws InvalidArgumentException If occurences is not a positive integer.
	 */
	private function mockCleanPage( $occurences = null ) {
		$expects = null;
		if ( $occurences === null ) {
			$expects = $this->any();
		} elseif ( !is_int( $occurences ) || $occurences < 0 ) {
			throw new InvalidArgumentException(
				'$occurences must be a positive integer' );
		} else {
			$expects = $this->exactly( $occurences );
		}

		$segmenterMock = $this->getMockBuilder( Segmenter::class )
			->setConstructorArgs( [ new RequestContext() ] )
			->onlyMethods( [ 'cleanPage' ] )
			->getMock();
		$segmenterMock
			->expects( $expects )
			->method( 'cleanPage' )
			->will( $this->returnValue( [] ) );
		$this->segmenter = $segmenterMock;
	}

	public function testSegmentPage_repeatedRequest_useCache() {
		// make sure we have a working cache
		$this->activateWanCache();
		// mock cleanPage with single call
		$this->mockCleanPage( 1 );

		$page = Util::addPage( 'Page', 'Foo' );
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

		$page = Util::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$this->segmenter->segmentPage( $title );
		$this->segmenter->segmentPage( $title, [], [] );
		$this->segmenter->segmentPage( $title, [], [ 'br' ] );
		$this->segmenter->segmentPage( $title, [ 'del' => true ], [] );
	}

	public function testSegmentPage_noTagParametersGiven_defaultUsed() {
		$this->setMwGlobals( [
			'wgWikispeechSegmentBreakingTags' => [ 'br' ],
			'wgWikispeechRemoveTags' => [ 'del' => true ]
		] );
		$titleString = 'Page';
		$content = 'one<br />two<del>three</del>';
		Util::addPage( $titleString, $content );
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

		$content = 'Foo';
		$page1 = Util::addPage( 'Page1', $content );
		$page2 = Util::addPage( 'Page2', $content );
		$title1 = $page1->getTitle();

		$revisionId2 = $page2->getLatest();
		$this->segmenter->segmentPage( $title1, [], [], $revisionId2 );
	}

	public function testSegmentPage_uncachedOldRevision_throwException() {
		// mock cleanPage with no calls
		$this->mockCleanPage( 0 );
		$this->expectException( MWException::class );

		$page = Util::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$revisionId = $page->getLatest();

		Util::editPage( $page, 'Bar' );
		$this->assertNotEquals( $revisionId, $page->getLatest() );
		$this->segmenter->segmentPage( $title, [], [], $revisionId );
	}

	public function testSegmentPage_cachedOldRevision_useCache() {
		// make sure we have a working cache
		$this->activateWanCache();
		// mock cleanPage with single calls
		$this->mockCleanPage( 1 );

		$page = Util::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$revisionId = $page->getLatest();
		$segments = $this->segmenter->segmentPage( $title, [], [], $revisionId );

		Util::editPage( $page, 'Bar' );
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

		$page = Util::addPage( 'Page', 'Foo' );
		$title = $page->getTitle();
		$this->segmenter->segmentPage( $title );
		$this->segmenter->segmentPage( $title, [ 'del' => true ], [ 'br' ] );
	}

	public function testGetSegment_segmentExists_returnSegment() {
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		Util::addPage( $titleString, $content );
		$title = Title::newFromText( $titleString );
		$hash = '33dc64326df9f4b281fc9d680f89423f3261d1056d857a8263d46f7904a705ac';
		$expectedSegment = [
			'startOffset' => 12,
			'endOffset' => 22,
			'content' => [ new CleanedText( 'Sentence 2.', './div/p/text()' ) ],
			'hash' => $hash
		];
		$segment = $this->segmenter->getSegment( $title, $hash );
		$this->assertEquals( $expectedSegment, $segment );
	}

	public function testGetSegment_segmentDoesntExists_returnNull() {
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		Util::addPage( $titleString, $content );
		$title = Title::newFromText( $titleString );
		$hash = 'ThisHashMatchesNoSegment';
		$segment = $this->segmenter->getSegment( $title, $hash );
		$this->assertNull( $segment );
	}
}

<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\SegmentBreak;
use MediaWiki\Wikispeech\Segment\Segmenter;
use MediaWikiUnitTestCase;
use RequestContext;
use WANObjectCache;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @covers \MediaWiki\Wikispeech\Segment\Segmenter
 */
class SegmenterTest extends MediaWikiUnitTestCase {

	/**
	 * @var TestingAccessWrapper|Segmenter
	 */
	private $segmenter;

	protected function setUp() : void {
		parent::setUp();
		$this->segmenter = TestingAccessWrapper::newFromObject(
			new Segmenter(
				new RequestContext(),
				$this->createStub( WANObjectCache::class ),
				$this->createMock( HttpRequestFactory::class )
			)
		);
	}

	public function testSegmentSentences_multiSentenceNode_giveMultipleSegments() {
		$cleanedContent = [
			new CleanedText( 'Sentence 1. Sentence 2. Sentence 3.' )
		];
		$expectedSegments = [
			new Segment(
				[ new CleanedText( 'Sentence 1.' ) ],
				0,
				10,
				'76ca3069cee56491f5b2f465c4e9b57b7fb74ebc12eecc0cd6aad965ea7e247e'
			),
			new Segment(
				[ new CleanedText( 'Sentence 2.' ) ],
				12,
				22,
				'33dc64326df9f4b281fc9d680f89423f3261d1056d857a8263d46f7904a705ac'
			),
			new Segment(
				[ new CleanedText( 'Sentence 3.' ) ],
				24,
				34,
				'bae6b55875cd8e8bee3b760773f36a3a25e2d6fa102f168aade3d49f77c34da6'
			)
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	/**
	 * Tests that a given string is not segmented.
	 *
	 * @dataProvider provideTestSegmentSentences_dontSegment
	 * @param string $text The string to be tested.
	 */
	public function testSegmentSentences_dontSegment( $text ) {
		$cleanedContent = [ new CleanedText( $text ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( $text ) ],
			$segments[0]->getContent()
		);
	}

	public function provideTestSegmentSentences_dontSegment() {
		return [
			'ellipses' => [ 'This is... one sentence.' ],
			'abbreviation' => [ 'One sentence i.e. one segment.' ],
			'dotDirectlyFollowedByComma' => [ 'As with etc., jr. and friends.' ],
			'decimalDot' => [ 'In numbers like 2.9.' ],
		];
	}

	public function testSegmentSentences_notSentenceFinalCharacter_keepLastSegment() {
		$cleanedContent = [ new CleanedText( 'Sentence. No sentence final' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'No sentence final' ) ],
			$segments[1]->getContent()
		);
		$this->assertSame( 26, $segments[1]->getEndOffset() );
	}

	public function testSegmentSentences_sentenceSplitIntoMultipleNodes_giveSingleSegment() {
		$cleanedContent = [
			new CleanedText( 'Sentence split ' ),
			new CleanedText( 'by' ),
			new CleanedText( ' tags.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			0,
			$segments[0]->getStartOffset()
		);
		$this->assertSame(
			5,
			$segments[0]->getEndOffset()
		);
		$this->assertSame(
			'Sentence split ',
			$segments[0]->getContent()[0]->getString()
		);
		$this->assertSame(
			'by',
			$segments[0]->getContent()[1]->getString()
		);
		$this->assertSame(
			' tags.',
			$segments[0]->getContent()[2]->getString()
		);
	}

	public function testSegmentSentences_sentenceSplitIntoMultipleNodes_giveStartOffset() {
		$cleanedContent = [
			new CleanedText( 'First sentence. Split' ),
			new CleanedText( 'sentence. And other sentence.' ),
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			16,
			$segments[1]->getStartOffset()
		);
		$this->assertSame(
			10,
			$segments[2]->getStartOffset()
		);
	}

	public function testSegmentSentences_simpleSentence_giveOffsets() {
		$cleanedContent = [ new CleanedText( 'Sentence.' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame( 0, $segments[0]->getStartOffset() );
		$this->assertSame( 8, $segments[0]->getEndOffset() );
	}

	public function testSegmentSentences_nodeContainsUnicodeChars_giveSegmentsAndOffsets() {
		$cleanedContent = [
			new CleanedText(
				'Normal sentence. Utterance with å. Another normal sentence.'
			)
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Utterance with å.',
			$segments[1]->getContent()[0]->getString()
		);
		$this->assertSame( 17, $segments[1]->getStartOffset() );
		$this->assertSame( 33, $segments[1]->getEndOffset() );
		$this->assertSame(
			'Another normal sentence.',
			$segments[2]->getContent()[0]->getString()
		);
		$this->assertSame( 35, $segments[2]->getStartOffset() );
		$this->assertSame( 58, $segments[2]->getEndOffset() );
	}

	public function testSegmentSentences_nodeStartsWithSentenceFinalCharacter_includeInPriorSegment() {
		$cleanedContent = [
			new CleanedText( 'Sentence one' ),
			new CleanedText( '. Sentence two.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Sentence one',
			$segments[0]->getContent()[0]->getString()
		);
		$this->assertSame(
			'.',
			$segments[0]->getContent()[1]->getString()
		);
		$this->assertSame(
			'Sentence two.',
			$segments[1]->getContent()[0]->getString()
		);
	}

	public function testSegmentSentences_trailingWhitespaceInNode_dontCreateEmptySegment() {
		$cleanedContent = [
			new CleanedText( 'Sentence 1. ' ),
			new CleanedText( 'Sentence 2.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Sentence 1.',
			$segments[0]->getContent()[0]->getString()
		);
		$this->assertSame(
			'Sentence 2.',
			$segments[1]->getContent()[0]->getString()
		);
	}

	public function testSegmentSentences_whitespaceBetweenSegmentBreakingTags_dontCreateEmptySegment() {
		$cleanedContent = [
			new CleanedText( 'Text one' ),
			new SegmentBreak(),
			new CleanedText( ' ' ),
			new SegmentBreak(),
			new CleanedText( 'Text two' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Text one',
			$segments[0]->getContent()[0]->getString()
		);
		$this->assertSame(
			'Text two',
			$segments[1]->getContent()[0]->getString()
		);
	}

	public function testSegmentSentences_nodeWithOnlyWhitespace_dontIncludeInSegments() {
		$cleanedContent = [
			new CleanedText( ' ' ),
			new CleanedText( 'Sentence 1.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Sentence 1.',
			$segments[0]->getContent()[0]->getString()
		);
	}

	public function testSegmentSentences_segmentWithOnlyWhitespace_dontAddSegment() {
		$cleanedContent = [ new CleanedText( "  \n" ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertCount( 0, $segments );
	}

	public function testSegmentSentences_leadingAndTrailingWhitespacesInNode_dontIncludeInSegments() {
		$cleanedContent = [ new CleanedText( ' Sentence. ' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Sentence.',
			$segments[0]->getContent()[0]->getString()
		);
		$this->assertSame( 1, $segments[0]->getStartOffset() );
		$this->assertSame( 9, $segments[0]->getEndOffset() );
	}

	public function testSegmentSentences_nodeWithOnlyNewline_dontIncludeInSegments() {
		$cleanedContent = [
			new CleanedText( 'text' ),
			new CleanedText( "\n" )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertCount( 1, $segments[0]->getContent() );
		$this->assertSame(
			'text',
			$segments[0]->getContent()[0]->getString()
		);
	}

	public function testSegmentSentences_lastNodeIsOnlySentenceFinalCharacter_includeInPriorSegment() {
		$cleanedContent = [
			new CleanedText( 'Sentence one' ),
			new CleanedText( '. ' ),
			new CleanedText( 'Sentence two.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Sentence one',
			$segments[0]->getContent()[0]->getString()
		);
		$this->assertSame(
			'.',
			$segments[0]->getContent()[1]->getString()
		);
		$this->assertSame(
			'Sentence two.',
			$segments[1]->getContent()[0]->getString()
		);
	}

	public function testSegmentSentences_segmentBreakInsteadOfSentenceFinalCharacter_segmentSentencesBySegmentBreaks() {
		$cleanedContent = [
			new CleanedText( 'Header' ),
			new SegmentBreak(),
			new CleanedText( 'Paragraph one' ),
			new SegmentBreak(),
			new CleanedText( 'Paragraph two' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Header',
			$segments[0]->getContent()[0]->getString()
		);
		$this->assertSame(
			'Paragraph one',
			$segments[1]->getContent()[0]->getString()
		);
		$this->assertSame(
			'Paragraph two',
			$segments[2]->getContent()[0]->getString()
		);
	}

	public function testEvaluateHash_singleSentence_giveHash() {
		// SHA256 of "Word 1 Word 2 Word 3."
		$expectedHash = '4466ca9fbdfc6c9cf9c53de4e5e373d6b60d023338e9a9f9ff8e6ddaef36a3e4';
		$segments = $this->segmenter->segmentSentences(
			[ new CleanedText( 'Word 1 Word 2 Word 3.' ) ]
		);
		$hash = $this->segmenter->evaluateHash( $segments[0] );
		$this->assertSame( $expectedHash, $hash );
	}

	public function testCleanPage_contentAndTitleGiven_giveCleanedTextArray() {
		$title = 'Page';
		$content = '<p>Content</p>';

		$cleanedText = $this->segmenter->cleanPage( $title, $content, [], [] );

		$this->assertEquals(
			[
				new CleanedText( 'Page', '//h1/text()' ),
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

	public function testCleanPage_titleContainsElements_giveTitleXpath() {
		$title = '<i>Page</i>';

		$cleanedText = $this->segmenter->cleanPage( $title, '', [], [] );

		$this->assertEquals(
			new CleanedText( 'Page', '//h1/i/text()' ),
			$cleanedText[0]
		);
	}
}

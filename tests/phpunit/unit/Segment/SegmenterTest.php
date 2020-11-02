<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use HashBagOStuff;
use MediaWikiUnitTestCase;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\SegmentBreak;
use MediaWiki\Wikispeech\Segment\Segmenter;

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
				$this->createStub( HashBagOStuff::class )
			)
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

	/**
	 * Tests that a given string is not segmented.
	 *
	 * @dataProvider provideTestDontSegment
	 * @param string $text The string to be tested.
	 */
	public function testDontSegment( $text ) {
		$cleanedContent = [ new CleanedText( $text ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( $text ) ],
			$segments[0]['content']
		);
	}

	public function provideTestDontSegment() {
		return [
			'ellipses' => [ 'This is... one sentence.' ],
			'abbreviations' => [ 'One sentence i.e. one segment.' ],
			'dot directly followed by comma' => [ 'As with etc., jr. and friends.' ],
			'decimal dot' => [ 'In numbers like 2.9.' ],
		];
	}

	public function testKeepLastSegmentEvenIfNotEndingWithSentenceFinalCharacter() {
		$cleanedContent = [ new CleanedText( 'Sentence. No sentence final' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'No sentence final' ) ],
			$segments[1]['content']
		);
		$this->assertSame( 26, $segments[1]['endOffset'] );
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
		$this->assertSame(
			5,
			$segments[0]['endOffset']
		);
		$this->assertSame(
			'Sentence split ',
			$segments[0]['content'][0]->string
		);
		$this->assertSame(
			'by',
			$segments[0]['content'][1]->string
		);
		$this->assertSame(
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
		$this->assertSame(
			16,
			$segments[1]['startOffset']
		);
		$this->assertSame(
			10,
			$segments[2]['startOffset']
		);
	}

	public function testTextOffset() {
		$cleanedContent = [ new CleanedText( 'Sentence.' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame( 0, $segments[0]['startOffset'] );
		$this->assertSame( 8, $segments[0]['endOffset'] );
	}

	public function testSegmentTextWithUnicodeChars() {
		$cleanedContent = [
			new CleanedText(
				'Normal sentence. Utterance with å. Another normal sentence.'
			)
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Utterance with å.',
			$segments[1]['content'][0]->string
		);
		$this->assertSame( 17, $segments[1]['startOffset'] );
		$this->assertSame( 33, $segments[1]['endOffset'] );
		$this->assertSame(
			'Another normal sentence.',
			$segments[2]['content'][0]->string
		);
		$this->assertSame( 35, $segments[2]['startOffset'] );
		$this->assertSame( 58, $segments[2]['endOffset'] );
	}

	public function testTextStartsWithSentenceFinalCharacter() {
		$cleanedContent = [
			new CleanedText( 'Sentence one' ),
			new CleanedText( '. Sentence two.' )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Sentence one',
			$segments[0]['content'][0]->string
		);
		$this->assertSame(
			'.',
			$segments[0]['content'][1]->string
		);
		$this->assertSame(
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
		$this->assertSame(
			'Sentence 1.',
			$segments[0]['content'][0]->string
		);
		$this->assertSame(
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
		$this->assertSame(
			'Text one',
			$segments[0]['content'][0]->string
		);
		$this->assertSame(
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
		$this->assertSame(
			'Sentence 1.',
			$segments[0]['content'][0]->string
		);
	}

	public function testRemoveLeadingAndTrailingWhitespaces() {
		$cleanedContent = [ new CleanedText( ' Sentence. ' ) ];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertSame(
			'Sentence.',
			$segments[0]['content'][0]->string
		);
		$this->assertSame( 1, $segments[0]['startOffset'] );
		$this->assertSame( 9, $segments[0]['endOffset'] );
	}

	public function testDontAddOnlyNewlineItem() {
		$cleanedContent = [
			new CleanedText( 'text' ),
			new CleanedText( "\n" )
		];
		$segments = $this->segmenter->segmentSentences( $cleanedContent );
		$this->assertCount( 1, $segments[0]['content'] );
		$this->assertSame(
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
		$this->assertSame(
			'Sentence one',
			$segments[0]['content'][0]->string
		);
		$this->assertSame(
			'.',
			$segments[0]['content'][1]->string
		);
		$this->assertSame(
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
		$this->assertSame(
			'Header',
			$segments[0]['content'][0]->string
		);
		$this->assertSame(
			'Paragraph one',
			$segments[1]['content'][0]->string
		);
		$this->assertSame(
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
		$this->assertSame( $expectedHash, $hash );
	}
}

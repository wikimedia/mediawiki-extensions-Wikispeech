<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

require_once __DIR__ . '/../../includes/Segmenter.php';
require_once 'Util.php';

class SegmenterTest extends MediaWikiTestCase {
	public function testSegmentSentences() {
		$cleanedContent = [
			new CleanedText( 'Sentence 1. Sentence 2. Sentence 3.' )
		];
		$expectedSegments = [
			[
				'startOffset' => 0,
				'endOffset' => 10,
				'content' => [ new CleanedText( 'Sentence 1.' ) ]
			],
			[
				'startOffset' => 12,
				'endOffset' => 22,
				'content' => [ new CleanedText( 'Sentence 2.' ) ]
			],
			[
				'startOffset' => 24,
				'endOffset' => 34,
				'content' => [ new CleanedText( 'Sentence 3.' ) ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByEllipses() {
		$cleanedContent = [
			new CleanedText( 'This is... one sentence.' )
			];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'This is... one sentence.' ) ],
			$segments[0]['content']
		);
	}

	public function testDontSegmentByAbbreviations() {
		$cleanedContent = [ new CleanedText( 'One sentence i.e. one segment.' ) ];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'One sentence i.e. one segment.' ) ],
			$segments[0]['content']
		);
	}

	public function testDontSegmentByDotDirectlyFollowedByComma() {
		$cleanedContent = [ new CleanedText( 'As with etc., jr. and friends.' ) ];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'As with etc., jr. and friends.' ) ],
			$segments[0]['content']
		);
	}

	public function testDontSegmentByDecimalDot() {
		$cleanedContent = [ new CleanedText( 'In numbers like 2.9.' ) ];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
			[ new CleanedText( 'In numbers like 2.9.' ) ],
			$segments[0]['content']
		);
	}

	public function testKeepLastSegmentEvenIfNotEndingWithSentenceFinalCharacter() {
		$cleanedContent = [ new CleanedText( 'Sentence. No sentence final' ) ];
		$segments = Segmenter::segmentSentences( $cleanedContent );
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
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
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
		$segments = Segmenter::segmentSentences( $cleanedContent );
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
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( 0, $segments[0]['startOffset'] );
		$this->assertEquals( 8, $segments[0]['endOffset'] );
	}

	public function testSegmentTextWithUnicodeChars() {
		$cleanedContent = [
			new CleanedText(
				'Normal sentence. Utterance with å. Another normal sentence.'
			)
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
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
		$segments = Segmenter::segmentSentences( $cleanedContent );
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
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence 1.',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals(
			'Sentence 2.',
			$segments[1]['content'][0]->string
		);
	}

	public function testRemoveTextWithOnlyWhitespacesOutsideSegments() {
		$cleanedContent = [
			new CleanedText( ' ' ),
			new CleanedText( 'Sentence 1.' )
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence 1.',
			$segments[0]['content'][0]->string
		);
	}

	public function testRemoveLeadingAndTrailingWhitespaces() {
		$cleanedContent = [ new CleanedText( ' Sentence. ' ) ];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals(
			'Sentence.',
			$segments[0]['content'][0]->string
		);
		$this->assertEquals( 1, $segments[0]['startOffset'] );
		$this->assertEquals( 9, $segments[0]['endOffset'] );
	}

	public function testLastTextIsOnlySentenceFinalCharacter() {
		$cleanedContent = [
			new CleanedText( 'Sentence one' ),
			new CleanedText( '. ' ),
			new CleanedText( 'Sentence two.' )
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
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
}

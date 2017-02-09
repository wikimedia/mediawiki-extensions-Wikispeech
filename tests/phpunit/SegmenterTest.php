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
			new CleanedText( 'Sentence 1. Sentence 2.' )
		];
		$expectedSegments = [
			[
				'startOffset' => 0,
				'endOffset' => 11,
				'content' => [ new CleanedText( 'Sentence 1.' ) ]
			],
			[
				'startOffset' => 11,
				'endOffset' => 23,
				'content' => [ new CleanedText( ' Sentence 2.' ) ]
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
			[ new CleanedText( ' No sentence final' ) ],
			$segments[1]['content']
		);
		$this->assertEquals( 27, $segments[1]['endOffset'] );
	}

	public function testTextOffset() {
		$cleanedContent = [ new CleanedText( 'Sentence.' ) ];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( 0, $segments[0]['startOffset'] );
		$this->assertEquals( 9, $segments[0]['endOffset'] );
	}

	public function testTextOffsetMultipleUtterances() {
		$cleanedContent = [ new CleanedText( 'Sentence one. Sentence two.' ) ];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( 0, $segments[0]['startOffset'] );
		$this->assertEquals( 13, $segments[0]['endOffset'] );
		$this->assertEquals( 13, $segments[1]['startOffset'] );
		$this->assertEquals( 27, $segments[1]['endOffset'] );
	}
}

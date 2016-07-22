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
			'Sentence 1. Sentence 2.'
		];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [ 'Sentence 1.' ]
			],
			[
				'position' => 11,
				'content' => [ ' Sentence 2.' ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByEllipses() {
		$cleanedContent = [
			'This is... one sentence.'
			];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [ 'This is... one sentence.' ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByAbbreviations() {
		$cleanedContent = [ 'One sentence i.e. one segment.' ];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [ 'One sentence i.e. one segment.' ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByDotDirectlyFollowedByComma() {
		$cleanedContent = [ 'As with etc., jr. and friends.' ];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [ 'As with etc., jr. and friends.' ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByDecimalDot() {
		$cleanedContent = [ 'In numbers like 2.9.' ];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [ 'In numbers like 2.9.' ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testKeepLastSegmentEvenIfNotEndingWithSentenceFinalCharacter() {
		$cleanedContent = [ 'Recording sessions' ];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [ 'Recording sessions' ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testSegmentContainingTag() {
		$cleanedContent = [
			'Sentence with a ',
			Util::createStartTag( '<i>' ),
			'tag',
			new CleanedEndTag( '</i>' ),
			'.'
		];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [
					'Sentence with a ',
					Util::createStartTag( '<i>' ),
					'tag',
					new CleanedEndTag( '</i>' ),
					'.'
				]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testSegmentEndingWithTag() {
		$cleanedContent = [
			"There's a tag after this",
			new CleanedEmptyElementTag( '<br />' )
		];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [
					"There's a tag after this",
					new CleanedEmptyElementTag( '<br />' )
				]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testCalculatePosition() {
		$cleanedContent = [ 'Segment 1.', 'Segment 2.', 'Segment 3.' ];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [ 'Segment 1.' ]
			],
			[
				'position' => 10,
				'content' => [ 'Segment 2.' ]
			],
			[
				'position' => 20,
				'content' => [ 'Segment 3.' ]
			],
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testCalculatePositionWhenTagIsRemoved() {
		$cleanedContent = [
			'Sentence with a ',
			Util::createStartTag( '<del>', 'removed ' ),
			new CleanedEndTag( '</del>' ),
			'tag. Another sentence.'
		];
		$expectedSegments = [
			[
				'position' => 0,
				'content' => [
					'Sentence with a ',
					Util::createStartTag( '<del>', 'removed ' ),
					new CleanedEndTag( '</del>' ),
					'tag.',
				]
			],
			[
				'position' => 39,
				'content' => [ ' Another sentence.' ]
			]
		];
		$segments = Segmenter::segmentSentences( $cleanedContent );
		$this->assertEquals( $expectedSegments, $segments );
	}
}

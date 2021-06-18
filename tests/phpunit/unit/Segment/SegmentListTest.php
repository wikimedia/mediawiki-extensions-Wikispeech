<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\SegmentList;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Wikispeech\Segment\SegmentList
 */
class SegmentListTest extends MediaWikiUnitTestCase {

	public function testFindFirstItemByHash_hashOccurOnce_returnSegment() {
		$segments = new SegmentList(
			[
				new Segment(
					[],
					0, 5,
					'dontFindMe'
				),
				new Segment(
					[],
					10, 15,
					'findMe'
				)
			]
		);
		$segment = $segments->findFirstItemByHash( 'findMe' );
		$this->assertEquals( 'findMe', $segment->getHash() );
	}

	public function testFindFirstItemByHash_hashOccurMultipleTimes_returnFirstSegment() {
		$segments = new SegmentList(
			[
				new Segment(
					[],
					0, 5,
					'dontFindMe'
				),
				new Segment(
					[],
					10, 15,
					'findMe'
				),
				new Segment(
					[],
					20, 25,
					'findMe'
				)
			]
		);
		$segment = $segments->findFirstItemByHash( 'findMe' );
		$this->assertEquals( 'findMe', $segment->getHash() );
		$this->assertEquals( 10, $segment->getStartOffset() );
	}

	public function testFindFirstItemByHash_hashMissing_returnNull() {
		$segments = new SegmentList(
			[
				new Segment(
					[],
					0, 5,
					'hello'
				),
				new Segment(
					[],
					10, 15,
					'world'
				)
			]
		);
		$segment = $segments->findFirstItemByHash( 'wheresWaldo' );
		$this->assertNull( $segment );
	}

}

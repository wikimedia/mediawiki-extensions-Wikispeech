<?php

require_once __DIR__ . '/../../includes/Segmenter.php';

/**
 * @file
 * @ingroup Extensions
 * @license GPL-3.0+
 */

class SegmenterTest extends MediaWikiTestCase {

	public function testSegmentSentences() {
		// @codingStandardsIgnoreStart
		$input = "Blonde on Blonde is the seventh studio album by American singer-songwriter Bob Dylan, released on May 16, 1966, on Columbia Records. Recording sessions began in New York in October 1965 with numerous backing musicians, including members of Dylan's live backing band, the Hawks.";
		$expectedSegments = [
			'Blonde on Blonde is the seventh studio album by American singer-songwriter Bob Dylan, released on May 16, 1966, on Columbia Records.',
			"Recording sessions began in New York in October 1965 with numerous backing musicians, including members of Dylan's live backing band, the Hawks." ];
		// @codingStandardsIgnoreEnd
		$segments = Segmenter::segmentSentences( $input );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByEllipses() {
		$input = "I mean, in ten recording sessions, man, we didn't get one song...It was the band.";
		$expectedSegments = [
			"I mean, in ten recording sessions, man, we didn't get one song...It was the band." ];
		$segments = Segmenter::segmentSentences( $input );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByAbbreviations() {
		// @codingStandardsIgnoreStart
		$input = 'On February 15 the session began at 6&nbsp;p.m. but Dylan simply sat in the studio working on his lyrics while the musicians played cards, napped and chatted.';
		$expectedSegments = [
			'On February 15 the session began at 6&nbsp;p.m. but Dylan simply sat in the studio working on his lyrics while the musicians played cards, napped and chatted.' ];
		// @codingStandardsIgnoreEnd
		$segments = Segmenter::segmentSentences( $input );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByDotDirectlyFollowedByComma() {
		// @codingStandardsIgnoreStart
		$input = 'Two people had strongly recommended the Hawks to Dylan: Mary Martin, the executive secretary of Albert Grossman, and blues singer John Hammond, Jr., son of record producer John Hammond, who had signed Dylan to Columbia Records in 1961; the Hawks had backed the younger Hammond on his 1965 album So Many Roads.';
		$expectedSegments = [
			'Two people had strongly recommended the Hawks to Dylan: Mary Martin, the executive secretary of Albert Grossman, and blues singer John Hammond, Jr., son of record producer John Hammond, who had signed Dylan to Columbia Records in 1961; the Hawks had backed the younger Hammond on his 1965 album So Many Roads.' ];
		// @codingStandardsIgnoreEnd
		$segments = Segmenter::segmentSentences( $input );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontRemoveStringsWithoutDots() {
		$input = "Recording sessions\n\nBackground";
		$expectedSegments = [ 'Recording sessions', 'Background' ];
		$segments = Segmenter::segmentSentences( $input );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testSegmentParagraphs() {
		$input = "Recording sessions

Background
After the release of Highway 61 Revisited in August 1965, Dylan set ...";
		$expectedSegments = [
			'Recording sessions',
			'Background',
			'After the release of Highway 61 Revisited in August 1965, Dylan set ...' ];
		$segments = Segmenter::segmentParagraphs( $input );
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testDontSegmentByDecimalDot() {
		$input = 'the two-CD set went on sale for $18.99 and the three-CD version for $129.99';
		// @codingStandardsIgnoreStart
		$expectedSegments = [ 'the two-CD set went on sale for $18.99 and the three-CD version for $129.99' ];
		// @codingStandardsIgnoreEnd
		$segments = Segmenter::segmentParagraphs( $input );
		$this->assertEquals( $expectedSegments, $segments );
	}
}

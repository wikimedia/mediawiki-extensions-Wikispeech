<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment;

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\Cleaner;
use MediaWiki\Wikispeech\Segment\StandardSegmenter;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Wikispeech\Segment\StandardSegmenter
 */
class StandardSegmenterLanguageTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideTestLanguageData
	 * @param string $plainText Input as plain text to be segmented.
	 * @param string[] $expectedSegmentsText Plain text value of expected segments.
	 */
	public function testSegmentSentences_languageText_isWellSegmented(
		string $plainText,
		array $expectedSegmentsText
	) {
		$cleaner = new Cleaner( [], [], false );
		$segmenter = new StandardSegmenter();
		$segments = $segmenter->segmentSentences( $cleaner->cleanHtml( $plainText ) );
		foreach ( $expectedSegmentsText as $i => $expectedSegmentText ) {
			$this->assertSame( $expectedSegmentText, $segments[$i]->getContent()[0]->getString() );
		}
	}

	/**
	 * @return array[]
	 */
	public static function provideTestLanguageData() {
		return [
			'Swedish' => [
				'Räksmörgås eller räkmacka är en smörgås med räkor som pålägg. Förutom räkor ingår ofta pålägg som t.ex. "majonnäs", ägg, kaviar och citron. En räksmörgås kan vara formad som en "landgång".',
				[
					'Räksmörgås eller räkmacka är en smörgås med räkor som pålägg.',
					'Förutom räkor ingår ofta pålägg som t.ex. "majonnäs", ägg, kaviar och citron.',
					'En räksmörgås kan vara formad som en "landgång".'
				]
			],
			'English' => [
				'A pangram or holoalphabetic sentence is a sentence using every letter of a given alphabet at least once. Pangrams have been used for many reasons, e.g. to display typefaces, test equipment, and develop skills in handwriting, calligraphy, and keyboarding.',
				[
					'A pangram or holoalphabetic sentence is a sentence using every letter of a given alphabet at least once.',
					'Pangrams have been used for many reasons, e.g. to display typefaces, test equipment, and develop skills in handwriting, calligraphy, and keyboarding.'
				]
			],
			'Arabic' => [
				'جامع الحروف عند البلغاء يطلق على الكلام المركب من جميع حروف التهجي بدون تكرار أحدها في لفظ واحد، أما في لفظين فهو جائز. ويجوز إطلاقه على ما جمعت فيه حروف التهجي إطلاقا.',
				[
					'جامع الحروف عند البلغاء يطلق على الكلام المركب من جميع حروف التهجي بدون تكرار أحدها في لفظ واحد، أما في لفظين فهو جائز.',
					'ويجوز إطلاقه على ما جمعت فيه حروف التهجي إطلاقا.'
				]
			],
			'Basque' => [
				'Laburdura gehienak letra xehez idazten dira, baina ez den-denak P.D. post data. P.S. post scriptum jn. (jauna), and. (andrea), K. kalea.',
				[
					'Laburdura gehienak letra xehez idazten dira, baina ez den-denak P.D. post data.',
					'P.S. post scriptum jn. (jauna), and. (andrea), K. kalea.'
				]
			]
		];
	}

}

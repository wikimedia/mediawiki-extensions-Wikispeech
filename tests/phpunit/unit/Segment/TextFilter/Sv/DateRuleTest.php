<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\Sv\DateRule;
use MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\RegexFilterRuleTest;

/**
 * @since 0.1.10
 * @covers \MediaWiki\Wikispeech\Segment\TextFilter\Sv\DateRule
 */
class DateRuleTest extends RegexFilterRuleTest {

	/**
	 * @dataProvider provider
	 * @param string $input
	 * @param string|null $alias
	 * @param bool $shouldMatch
	 */
	public function test( string $input, ?string $alias, bool $shouldMatch ) {
		$this->assertRegexFilterRule( new DateRule(), $input, $alias, $shouldMatch );
	}

	public function provider(): array {
		return [
			'1 jan 1001' => [ '1 januari 1001', 'första januari tusen ett', true ],
			'1 januari 1001' => [ '1 januari 1001', 'första januari tusen ett', true ],
			'2 feb 1001' => [ '2 feb 1001', 'andra februari tusen ett', true ],
			'2 februari 1001' => [ '2 februari 1001', 'andra februari tusen ett', true ],
			'3 mar 1001' => [ '3 mar 1001', 'tredje mars tusen ett', true ],
			'3 mars 1001' => [ '3 mars 1001', 'tredje mars tusen ett', true ],
			'4 apr 1001' => [ '4 apr 1001', 'fjärde april tusen ett', true ],
			'4 april 1001' => [ '4 april 1001', 'fjärde april tusen ett', true ],
			'5 maj 1001' => [ '5 maj 1001', 'femte maj tusen ett', true ],
			'6 jun 1001' => [ '6 jun 1001', 'sjätte juni tusen ett', true ],
			'6 juni 1001' => [ '6 juni 1001', 'sjätte juni tusen ett', true ],
			'7 jul 1001' => [ '7 jul 1001', 'sjunde juli tusen ett', true ],
			'7 juli 1001' => [ '7 juli 1001', 'sjunde juli tusen ett', true ],
			'8 aug 1001' => [ '8 aug 1001', 'åttonde augusti tusen ett', true ],
			'8 augusti 1001' => [ '8 augusti 1001', 'åttonde augusti tusen ett', true ],
			'9 sep 1001' => [ '9 sep 1001', 'nionde september tusen ett', true ],
			'9 september 1001' => [ '9 september 1001', 'nionde september tusen ett', true ],
			'10 okt 1001' => [ '10 okt 1001', 'tionde oktober tusen ett', true ],
			'10 oktober 1001' => [ '10 oktober 1001', 'tionde oktober tusen ett', true ],
			'11 nov 1001' => [ '11 nov 1001', 'elfte november tusen ett', true ],
			'11 november 1001' => [ '11 november 1001', 'elfte november tusen ett', true ],
			'12 dec 1001' => [ '12 dec 1001', 'tolfte december tusen ett', true ],
			'12 december 1001' => [ '12 december 1001', 'tolfte december tusen ett', true ],
		];
	}

}

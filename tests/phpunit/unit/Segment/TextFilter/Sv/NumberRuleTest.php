<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\Sv\NumberRule;
use MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\RegexFilterRuleTestBase;

/**
 * @since 0.1.10
 * @covers \MediaWiki\Wikispeech\Segment\TextFilter\Sv\NumberRule
 */
class NumberRuleTest extends RegexFilterRuleTestBase {

	/**
	 * @dataProvider provider
	 * @param string $input
	 * @param string|null $alias
	 * @param bool $shouldMatch
	 */
	public function test( string $input, ?string $alias, bool $shouldMatch ) {
		$this->assertRegexFilterRule( new NumberRule(), $input, $alias, $shouldMatch );
	}

	public static function provider(): array {
		return [
			'float' => [ '123,45', 'ett hundra tjugotre komma fyrtiofem', true ],
			'int' => [ '123', 'ett hundra tjugotre', true ],
			'space magnitudes' => [ '292 000', 'två hundra nittiotvå tusen', true ],
			'space magnitudes unicode' => [ '3 815', 'tre tusen åtta hundra femton', true ],
			'dot magnitudes' => [ '293.000', 'två hundra nittiotre tusen', true ],
		];
	}
}

<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\Sv\YearRangeRule;
use MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\RegexFilterRuleTest;

/**
 * @since 0.1.10
 * @covers \MediaWiki\Wikispeech\Segment\TextFilter\Sv\YearRangeRule
 */
class YearRangeRuleTest extends RegexFilterRuleTest {

	/**
	 * @dataProvider provider
	 * @param string $input
	 * @param string|null $alias
	 * @param bool $shouldMatch
	 */
	public function test( string $input, ?string $alias, bool $shouldMatch ) {
		$this->assertRegexFilterRule( new YearRangeRule(), $input, $alias, $shouldMatch );
	}

	public function provider(): array {
		return [
			'bad separator' => [ '1000/1100', 'will not match', false ],
			'em dash' => [ '1000–1100', 'tusen till elva hundra', true ],
			'dash' => [ '1000-1100', 'tusen till elva hundra', true ],
			'From is more than to' => [ '1100–1000', 'elva hundra till tusen', true ],
			'From is equals to to' => [ '1000–1000', 'tusen till tusen', true ],
			'From is less than to' => [ '1000–1100', 'tusen till elva hundra', true ],
		];
	}

}

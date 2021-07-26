<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\Sv\YearRule;
use MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\RegexFilterRuleTest;

/**
 * @since 0.1.10
 * @covers \MediaWiki\Wikispeech\Segment\TextFilter\Sv\YearRule
 */
class YearRuleTest extends RegexFilterRuleTest {

	/**
	 * @dataProvider provider
	 * @param string $input
	 * @param string|null $alias
	 * @param bool $shouldMatch
	 */
	public function test( string $input, ?string $alias, bool $shouldMatch ) {
		$this->assertRegexFilterRule( new YearRule(), $input, $alias, $shouldMatch );
	}

	public function provider(): array {
		return [
			'Number, not a year' => [ '123 456 789', 'will not match', false ],
			'Before 1000 does not match' => [ '999', 'will not match', false ],
			'After 2099 match but breaks rules' => [ '2100', null, true ],
			'1000' => [ '1000', 'tusen', true ],
			'1100' => [ '1100', 'elva hundra', true ],
			'1200' => [ '1200', 'tolv hundra', true ],
			'1300' => [ '1300', 'tretton hundra', true ],
			'1400' => [ '1400', 'fjorton hundra', true ],
			'1500' => [ '1500', 'femton hundra', true ],
			'1600' => [ '1600', 'sexton hundra', true ],
			'1700' => [ '1700', 'sjutton hundra', true ],
			'1800' => [ '1800', 'arton hundra', true ],
			'1891' => [ '1891', 'arton hundra nittioett', true ],
			'1900' => [ '1900', 'nitton hundra', true ],
			'2000' => [ '2000', 'tjugo hundra', true ],
			'2002' => [ '2002', 'tjugo hundra två', true ],
			'2052' => [ '2052', 'tjugo hundra femtiotvå', true ],
		];
	}

}

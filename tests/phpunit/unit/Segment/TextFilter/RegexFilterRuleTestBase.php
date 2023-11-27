<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\RegexFilterRule;
use MediaWikiUnitTestCase;

/**
 * @since 0.1.10
 */
abstract class RegexFilterRuleTestBase extends MediaWikiUnitTestCase {

	/**
	 * @param RegexFilterRule $rule
	 * @param string $input
	 * @param string|null $alias
	 * @param bool $shouldMatch
	 */
	public function assertRegexFilterRule(
		RegexFilterRule $rule,
		string $input,
		?string $alias,
		bool $shouldMatch
	) {
		$preg_matched = preg_match(
			$rule->getExpression(),
			$input,
			$matches,
			PREG_OFFSET_CAPTURE
		);
		$this->assertSame( $shouldMatch, ( $preg_matched === 1 ) );
		if ( $shouldMatch ) {
			$this->assertSame( $alias, $rule->createAlias( $matches ) );
		}
	}

}

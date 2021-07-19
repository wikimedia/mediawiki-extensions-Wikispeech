<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\RegexFilterRule;

/**
 * @since 0.1.10
 */
class NumberRule extends RegexFilterRule {

	/**
	 * @since 0.1.10
	 */
	public function __construct() {
		parent::__construct(
			'/(^|\D)' .
			'(?P<main>' .
			'(?P<integer>(\d{1,3})(([ .])\d{3}(\4\d{3})*)?)' .
			'((?P<comma>,)(?P<decimals>\d+))?' .
			')' .
			'(\D|$)/',
			'main'
		);
	}

	/**
	 * @since 0.1.10
	 * @param array $matches
	 * @return string|null
	 */
	public function createAlias( array $matches ): ?string {
		// remove all whitespaces and magnitude separating dots
		$integer = intval( str_replace( [ '.', ' ' ], '', $matches['integer'][0] ) );
		$decimals = $matches['comma'][0] === ',' ? $matches['decimals'][0] : null;
		$digitsToWords = new DigitsToSwedishWords();
		return $digitsToWords->stringFloatToWords( $integer, $decimals );
	}

}

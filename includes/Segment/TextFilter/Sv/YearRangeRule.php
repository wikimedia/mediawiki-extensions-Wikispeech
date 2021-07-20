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
class YearRangeRule extends RegexFilterRule {

	/**
	 * @since 0.1.10
	 */
	public function __construct() {
		parent::__construct(
			'/(^|\D)(?P<main>(?P<fromYear>\d{3,4})(–|-)(?P<toYear>\d{2,4}))(\D|$)/',
			'main'
		);
	}

	/**
	 * @since 0.1.10
	 * @param array $matches
	 * @return string|null
	 */
	public function createAlias( array $matches ): ?string {
		// todo allow for years before 100?
		// todo assert that from-year is less than to-year?
		$fromYear = YearRule::getYearAlias( $matches['fromYear'][0] );
		$toDigits = $matches['toYear'][0];
		if ( strlen( $toDigits ) === 2 ) {
			$digitsToWords = new DigitsToSwedishWords();
			$toYear = $digitsToWords->intToWords( intval( $toDigits ) );
		} else {
			$toYear = YearRule::getYearAlias( $toDigits );
		}
		if ( $fromYear === null || $toYear === null ) {
			// @todo log unsupported from- and/or to-year
			return null;
		}
		return $fromYear . ' till ' . $toYear;
	}

}

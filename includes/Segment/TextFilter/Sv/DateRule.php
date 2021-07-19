<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\RegexFilterRule;
use RuntimeException;

/**
 * @since 0.1.10
 */
class DateRule extends RegexFilterRule {

	/**
	 * @since 0.1.10
	 */
	public function __construct() {
		parent::__construct(
			'/(^|\D)(?P<main>'
			. '(?P<dayOfMonth>\d{1,2})'
			. '\s+'
			. '('
			. '(jan(uari)?)'
			. '|(feb(ruari)?)'
			. '|(mar(s)?)'
			. '|(apr(il)?)'
			. '|(maj)'
			. '|(jun(i)?)'
			. '|(jul(i)?)'
			. '|(aug(usti)?)'
			. '|(sep(tember)?)'
			. '|(okt(ober)?)'
			. '|(nov(ember)?)'
			. '|(dec(ember)?)'
			. ')'
			. '\s+'
			. '(?P<year>\d{1,4})'
			. ')(\D|$)/i',
			'main'
		);
	}

	/**
	 * @since 0.1.10
	 * @param array $matches
	 * @return string|null
	 */
	public function createAlias( array $matches ): ?string {
		$digitsToWords = new DigitsToSwedishWords();

		$dayOfMonth = intval( $matches['dayOfMonth'][0] );

		$alias = $digitsToWords->intToOrdinal( $dayOfMonth );
		if ( $alias === null ) {
			// @todo log?
			return null;
		}

		$alias .= ' ';

		if ( $matches[5][0] !== '' ) {
			$alias .= 'januari';
		} elseif ( $matches[7][0] !== '' ) {
			$alias .= 'februari';
		} elseif ( $matches[9][0] !== '' ) {
			$alias .= 'mars';
		} elseif ( $matches[11][0] !== '' ) {
			$alias .= 'april';
		} elseif ( $matches[13][0] !== '' ) {
			$alias .= 'maj';
		} elseif ( $matches[14][0] !== '' ) {
			$alias .= 'juni';
		} elseif ( $matches[16][0] !== '' ) {
			$alias .= 'juli';
		} elseif ( $matches[18][0] !== '' ) {
			$alias .= 'augusti';
		} elseif ( $matches[20][0] !== '' ) {
			$alias .= 'september';
		} elseif ( $matches[22][0] !== '' ) {
			$alias .= 'oktober';
		} elseif ( $matches[24][0] !== '' ) {
			$alias .= 'november';
		} elseif ( $matches[26][0] !== '' ) {
			$alias .= 'december';
		} else {
			throw new RuntimeException( 'Bad code: Month match out of sync with regular expression.' );
		}

		$alias .= ' ';

		$year = YearRule::getYearAlias( $matches['year'][0] );
		if ( $year === null ) {
			// @todo log?
			return null;
		}
		$alias .= $year;

		return $alias;
	}

}

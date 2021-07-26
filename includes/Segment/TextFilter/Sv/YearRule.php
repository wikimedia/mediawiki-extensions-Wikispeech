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
class YearRule extends RegexFilterRule {

	/**
	 * @since 0.1.10
	 */
	public function __construct() {
		parent::__construct(
			'/(^|\D)(?P<main>\d{4})(\D|$)/',
			'main'
		);
	}

	/**
	 * @since 0.1.10
	 * @param array $matches
	 * @return string|null
	 */
	public function createAlias( array $matches ): ?string {
		return self::getYearAlias( $matches['main'][0] );
	}

	/**
	 * @since 0.1.10
	 * @param string $year
	 * @return string|null Null if unable to parse
	 */
	public static function getYearAlias( string $year ): ?string {
		// @todo assert $year is all numbers
		$digitsToWords = new DigitsToSwedishWords();
		if ( strlen( $year ) < 4 ) {
			return $digitsToWords->intToWords( intval( $year ) );
		}
		$alias = '';
		$mille = substr( $year, 0, 2 );
		if ( $mille === '10' ) {
			$alias .= 'tusen';
		} elseif ( $mille === '11' ) {
			$alias .= 'elva hundra';
		} elseif ( $mille === '12' ) {
			$alias .= 'tolv hundra';
		} elseif ( $mille === '13' ) {
			$alias .= 'tretton hundra';
		} elseif ( $mille === '14' ) {
			$alias .= 'fjorton hundra';
		} elseif ( $mille === '15' ) {
			$alias .= 'femton hundra';
		} elseif ( $mille === '16' ) {
			$alias .= 'sexton hundra';
		} elseif ( $mille === '17' ) {
			$alias .= 'sjutton hundra';
		} elseif ( $mille === '18' ) {
			$alias .= 'arton hundra';
		} elseif ( $mille === '19' ) {
			$alias .= 'nitton hundra';
		} elseif ( $mille === '20' ) {
			$alias .= 'tjugo hundra';
		} else {
			// @todo log ( 'Unsupported mille: ' . $mille );
			return null;
		}

		$cent = substr( $year, 2 );
		if ( $cent !== '00' ) {
			$centWords = $digitsToWords->intToWords( intval( $cent ) );
			if ( $centWords === null ) {
				// @todo log?
				return null;
			}
			$alias .= ' ' . $centWords;
		}
		return $alias;
	}

}

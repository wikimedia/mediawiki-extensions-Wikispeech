<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.10
 */
abstract class AbstractDigitsToWords implements DigitsToWords {

	/**
	 * Translate floating point to text value, e.g. 3.1415 -> 'three point one four one five'.
	 * There are limitations to this method due to PHP transforming 3.00 to 3.
	 *
	 * @see DigitsToWords::stringFloatToWords() To avoid limitations of PHP.
	 * @since 0.1.10
	 * @param float $input
	 * @return string|null Null if input number is not supported
	 */
	public function floatToWords( float $input ): ?string {
		$integerAndDecimals = explode( '.', strval( $input ) );
		return $this->stringFloatToWords(
			intval( $integerAndDecimals[0] ),
			count( $integerAndDecimals ) === 2 ? $integerAndDecimals[1] : null
		);
	}

}

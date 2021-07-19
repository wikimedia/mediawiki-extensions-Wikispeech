<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Translates numeric values to written text in different forms.
 *
 * @since 0.1.10
 */
interface DigitsToWords {

	/**
	 * Translate integer to ordinal text value, e.g. 1 -> 'first', 2 -> 'second'.
	 *
	 * @since 0.1.10
	 * @param int $input
	 * @return string|null Null if input number is not supported
	 */
	public function intToOrdinal( int $input ): ?string;

	/**
	 * Translate floating point to text value, e.g. 3.1415 -> 'three point one four one five'.
	 * There are limitations to this method due to PHP transforming 3.00 to 3.
	 *
	 * @see DigitsToWords::stringFloatToWords() To avoid limitations of PHP.
	 * @since 0.1.10
	 * @param float $input
	 * @return string|null Null if input number is not supported
	 */
	public function floatToWords( float $input ): ?string;

	/**
	 * Translate floating point to text value, e.g. 3.00 -> 'three point zero zero'.
	 *
	 * @since 0.1.10
	 * @param int $integer Integer part of the floating value
	 * @param string|null $decimals Decimals part of the floating value as string value
	 * @return string|null Null if input number is not supported
	 */
	public function stringFloatToWords(
		int $integer,
		?string $decimals = null
	): ?string;

	/**
	 * Translate integer to text value, e.g. 1 -> 'one', 13 -> 'thirteen'.
	 *
	 * @since 0.1.10
	 * @param int $input
	 * @return string|null Null if input value is not supported.
	 */
	public function intToWords( int $input ): ?string;

}

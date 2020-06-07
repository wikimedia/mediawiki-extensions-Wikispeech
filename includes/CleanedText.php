<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Holds information about a text node that has been cleaned, to be
 * used during recitation.
 *
 * @since 0.0.1
 */

class CleanedText {
	/**
	 * The text content from the text node this was created from.
	 *
	 * @var string $string
	 */

	public $string;

	/**
	 * The XPath expression for the text node that this was created
	 * from.
	 *
	 * @var array $path
	 */

	public $path;

	/**
	 * Create a CleanedText, given a string representation.
	 *
	 * If the path isn't set, it defaults to the empty string.
	 *
	 * @since 0.0.1
	 * @param string $string The string representation of this text.
	 * @param array $path The path to the text node this was created from.
	 */
	public function __construct( $string, $path = '' ) {
		$this->string = $string;
		$this->path = $path;
	}
}

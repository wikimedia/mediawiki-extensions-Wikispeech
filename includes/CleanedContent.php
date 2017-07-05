<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
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
	function __construct( $string, $path='' ) {
		$this->string = $string;
		$this->path = $path;
	}

	/**
	 * Create a Text element from the content.
	 *
	 * Path is converted to a string and added as an attribute.
	 *
	 * @since 0.0.1
	 * @param DOMDocument $dom The DOM Document used to create the element.
	 * @return DOMElement The created text element.
	 */
	function toElement( $dom ) {
		$element = $dom->createElement( 'text', $this->string );
		$element->setAttribute( 'path', $this->path );
		return $element;
	}
}

/**
 * Denotes a break between to segments. Added by `Cleaner` and
 * consumed by `Segmenter`.
 *
 * @since 0.0.1
 */

class SegmentBreak {
}

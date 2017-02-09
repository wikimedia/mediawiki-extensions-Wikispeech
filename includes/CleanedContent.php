<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

abstract class CleanedContent {
	/**
	 * The string representation of the content, as it is written in
	 * the HTML. This includes the tag name, any attributes, and the
	 * brackets, if content is a tag.
	 *
	 * @var string $string
	 */

	public $string;

	/**
	 * Create a CleanedContent, given a string representation.
	 *
	 * @since 0.0.1
	 * @param string $string The string representation of this content.
	 */

	function __construct( $string ) {
		$this->string = $string;
	}
}

class CleanedTag extends CleanedContent {
}

class CleanedText extends CleanedContent {
	/**
	 * The path in the HTML to the text node that this was created
	 * from. The path consists of indices of the elements leading to
	 * the text node, and the index of the text node itself.
	 *
	 * @var array $path
	 */

	public $path;

	/**
	 * Create a CleanedText, given a string representation.
	 *
	 * If the path isn't set, it defaults to the empty array.
	 *
	 * @since 0.0.1
	 * @param string $string The string representation of this text.
	 * @param array $path The path to the text node this was created from.
	 */

	function __construct( $string, $path=[] ) {
		parent::__construct( $string );
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
		$pathString = implode( $this->path, ',' );
		$element->setAttribute( 'path', $pathString );
		return $element;
	}
}

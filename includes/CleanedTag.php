<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

abstract class CleanedTag {

	/**
	 * The string representation of the tag, as it is written in the
	 * HTML. This includes the tag name, any attributes, and the
	 * brackets.
	 *
	 * @var string $tagString
	 */

	public $tagString;

	function __construct( $tagString ) {
		$this->tagString = $tagString;
	}

	/**
	 * Get the length of the tag string.
	 *
	 * @since 0.0.1
	 * @return int The length of the tag string.
	 */

	function getLength() {
		return strlen( $this->tagString );
	}
}

class CleanedStartTag extends CleanedTag {

	/**
	 * The length of the element content, i.e. the string delimited by
	 * this start tag and the corresponding end tag.
	 *
	 * @var int $contentLength
	 */

	public $contentLength;

	function __construct( $tagString ) {
		parent::__construct( $tagString );
		$this->contentLength = 0;
	}

	/**
	 * Get the length of the tag string.
	 *
	 * @since 0.0.1
	 * @return int The length of the tag string, including element content.
	 */

	function getLength() {
		$length = strlen( $this->tagString );
		if ( $this->contentLength ) {
			$length += $this->contentLength;
		}
		return $length;
	}
}

class CleanedEndTag extends CleanedTag {
}

class CleanedEmptyElementTag extends CleanedTag {
}

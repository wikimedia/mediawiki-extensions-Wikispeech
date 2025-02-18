<?php

namespace MediaWiki\Wikispeech\Segment;

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

class CleanedText extends SegmentContent {
	/**
	 * The text content from the text node this was created from.
	 *
	 * @var string
	 */
	private $string;

	/**
	 * The XPath expression for the text node that this was created
	 * from.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Create a CleanedText, given a string representation.
	 *
	 * If the path isn't set, it defaults to the empty string.
	 *
	 * @since 0.0.1
	 * @param string $string The string representation of this text.
	 * @param string $path The path to the text node this was created from.
	 */
	public function __construct(
		string $string,
		string $path = ''
	) {
		$this->string = $string;
		$this->path = $path;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getString(): string {
		return $this->string;
	}

	/**
	 * @since 0.1.10
	 * @param string $string
	 */
	public function setString( string $string ): void {
		$this->string = $string;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @since 0.1.10
	 * @param string $path
	 */
	public function setPath( string $path ): void {
		$this->path = $path;
	}

}

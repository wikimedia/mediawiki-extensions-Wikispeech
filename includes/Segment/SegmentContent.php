<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.13 Change to abstract class
 * @since 0.1.10
 */
abstract class SegmentContent {
	/**
	 * The XPath expression for the text node that this was created
	 * from.
	 *
	 * @since 0.1.15
	 * @var string|null
	 */
	protected $path;

	/**
	 * @since 0.1.13
	 * @return string
	 */
	public function getString() {
		return '';
	}

	/**
	 * @since 0.1.15 return propety instead of always `null`.
	 * @since 0.1.13
	 * @return ?string
	 */
	public function getPath(): ?string {
		return $this->path;
	}

	/**
	 * @since 0.1.15
	 * @param ?string $path
	 */
	public function setPath( ?string $path ): void {
		$this->path = $path;
	}
}

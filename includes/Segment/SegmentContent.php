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
	 * @since 0.1.13
	 * @return string
	 */
	public function getString() {
		return '';
	}

	/**
	 * @since 0.1.13
	 * @return ?string
	 */
	public function getPath() {
		return null;
	}
}

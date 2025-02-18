<?php

namespace MediaWiki\Wikispeech\Segment\PartOfContent;

use MediaWiki\Wikispeech\Segment\SegmentContent;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Represents a link element in segments.
 *
 * This adds extra words that are read before the link text.
 *
 * @since 0.0.13
 */

class Link extends SegmentContent {
	/**
	 * @since 0.1.13
	 * @return string
	 */
	public function getString() {
		// Add spaces before and after to make sure it's not concatenated to
		// surrounding text.
		return ' ' . wfMessage( 'wikispeech-poc-link' )->text() . ' ';
	}
}

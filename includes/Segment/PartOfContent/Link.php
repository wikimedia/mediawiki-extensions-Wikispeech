<?php

namespace MediaWiki\Wikispeech\Segment\PartOfContent;

use DOMElement;

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
 * @since 0.1.13
 */
class Link extends PartOfContent {
	/**
	 * @since 0.1.13
	 * @return string
	 */
	public function getString() {
		// TODO: Use messages for surrounding strings.
		// Add spaces before and after to make sure it's not concatenated to
		// surrounding text.
		return ' ' . wfMessage( 'wikispeech-poc-link' )->text() . ' ';
	}

	/**
	 * @since 0.1.15
	 * @param DOMElement $element
	 * @return Link|null
	 */
	public static function fromElement( DOMElement $element ): self|null {
		if ( $element->nodeName === 'a' ) {
			return new Link();
		}

		return null;
	}
}

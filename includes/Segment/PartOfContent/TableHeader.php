<?php

namespace MediaWiki\Wikispeech\Segment\PartOfContent;

use DOMElement;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * A table header in segments.
 *
 * @since 0.1.15
 */
class TableHeader extends PartOfContent {
	/**
	 * Generate a string that will be read for a table header.
	 *
	 * In English it will look like " header: ".
	 *
	 * @since 0.1.15
	 * @return string
	 */
	public function getString() {
		return wfMessage( 'word-separator' )->text() .
			wfMessage( 'wikispeech-poc-table-header' )->text() .
			wfMessage( 'colon-separator' )->text() .
			wfMessage( 'word-separator' )->text();
	}

	/**
	 * Create an instance if `$element` is a th.
	 *
	 * @since 0.1.15
	 * @param DOMElement $element
	 * @return self | null
	 */
	public static function fromElement( DOMElement $element ): self|null {
		if ( $element->nodeName === 'th' ) {
			return new TableHeader();
		}

		return null;
	}
}
